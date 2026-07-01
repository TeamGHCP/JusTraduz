<?php

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Services\AuditService;
use App\Services\OrganizationInviteService;
use PDO;
use PDOException;

class RegisterService
{
    private const MIN_PASSWORD_LENGTH = 10;
    private PDO $pdo;
    private AuditService $audit;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->audit = new AuditService($pdo);
    }

    public function registrar(array $data): array
    {
        $nome   = trim((string) ($data['nome'] ?? ''));
        $email  = $this->normalizeEmail((string) ($data['email'] ?? ''));
        $telefone = trim((string) ($data['telefone'] ?? ''));
        $dataNascimento = trim((string) ($data['data_nascimento'] ?? ''));
        $maioridadeConfirmada = ($data['maioridade_confirmada'] ?? '') === '1';
        $cpf = preg_replace('/\D+/', '', (string) ($data['cpf'] ?? '')) ?? '';
        $senha  = trim((string) ($data['senha'] ?? ''));
        $senha2 = trim((string) ($data['senha2'] ?? ''));
        $tipo   = (string) ($data['tipo'] ?? 'cliente');
        $oab    = preg_replace('/\D+/', '', (string) ($data['inscricao'] ?? ''));
        $oab_uf = strtoupper(trim((string) ($data['oab_uf'] ?? '')));
        $oab_status = 'not_required';
        $oab_parametro = null;
        $oab_verificado = false;
        $oab_tipo = null;
        $status_cna = null;

        $pendingOfficeInvite = $this->pendingOfficeInviteRequirement();

        if ($pendingOfficeInvite !== null) {
            if ($email !== $this->normalizeEmail((string) $pendingOfficeInvite['email'])) {
                throw new ValidationException('Use o e-mail que recebeu o convite do escritório.');
            }
            if ($tipo !== 'advogado') {
                throw new ValidationException('Convites do plano Escritório são exclusivos para cadastro de advogado.');
            }
        }

        // Validações
        if (!$nome || !$email || !$senha) {
            throw new ValidationException('Preencha todos os campos obrigatórios.');
        }

        $ageError = $this->ageValidationError($dataNascimento, $maioridadeConfirmada);
        if ($ageError !== null) {
            throw new ValidationException($ageError);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Informe um e-mail válido.');
        }

        $telefone = preg_replace('/[^\d()+\-\s]/', '', $telefone) ?? '';
        if ($telefone === '' || strlen(preg_replace('/\D+/', '', $telefone) ?? '') < 10) {
            throw new ValidationException('Informe um telefone válido com DDD.');
        }

        if (!in_array($tipo, ['cliente', 'advogado'], true)) {
            throw new ValidationException('Escolha Cliente ou Advogado para continuar.');
        }

        if ($senha !== $senha2) {
            throw new ValidationException('As senhas não coincidem.');
        }

        $passwordError = $this->passwordValidationError($senha);
        if ($passwordError !== null) {
            throw new ValidationException($passwordError);
        }

        $isProfessional = $tipo === 'advogado';
        if ($tipo === 'cliente') {
            if (!$this->isValidCpf($cpf)) {
                throw new ValidationException('Informe um CPF valido para consultar seus processos.');
            }
        } else {
            $cpf = null;
        }

        if ($isProfessional) {
            $validUfs = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];

            if ($oab === '') {
                throw new ValidationException('Numero da OAB e obrigatorio.');
            }

            if (!in_array($oab_uf, $validUfs, true)) {
                throw new ValidationException('Informe a UF da OAB.');
            }

            $oab_status = 'pending';
            $oab_tipo = $tipo;
            $status_cna = 'pendente';
        } else {
            $oab = null;
            $oab_uf = null;
        }

        // Verifica se e-mail já existe
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new ValidationException('E-mail já cadastrado.');
        }

        if ($cpf && $this->cpfExists($cpf)) {
            throw new ValidationException('CPF já cadastrado.');
        }

        // Insere no banco
        $senhaCriptografada = $this->hashUserPassword($senha);

        $sql = "INSERT INTO users (nome, email, senha, tipo, telefone, cpf, oab, oab_uf, oab_status, oab_parametro, oab_verificado, oab_tipo, status_cna, oab_submitted_at, profile_completed)
                VALUES (:nome, :email, :senha, :tipo, :telefone, :cpf, :oab, :oab_uf, :oab_status, :oab_parametro, :oab_verificado, :oab_tipo, :status_cna, :oab_submitted_at, 1)";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':nome'   => $nome,
                ':email'  => $email,
                ':senha'  => $senhaCriptografada,
                ':tipo'   => $tipo,
                ':telefone' => $telefone ?: null,
                ':cpf' => $cpf ?: null,
                ':oab'    => $oab,
                ':oab_uf' => $oab_uf,
                ':oab_status' => $oab_status,
                ':oab_parametro' => $oab_parametro,
                ':oab_verificado' => $oab_verificado ? 1 : 0,
                ':oab_tipo' => $oab_tipo,
                ':status_cna' => $status_cna,
                ':oab_submitted_at' => $isProfessional ? date('Y-m-d H:i:s') : null,
            ]);
            $userId = (int) $this->pdo->lastInsertId();

            $this->audit->log('auth.register', 'user', $userId, [
                'email' => $email,
                'tipo' => $tipo,
                'oab_verificado' => $oab_verificado,
            ]);

            if ($tipo === 'advogado') {
                $this->logOabValidation($userId, 'cadastro', null, 'pendente', 'admin_manual', $oab_status);
                // System notification triggers or mail notifications
                if (class_exists('App\Services\MailerService')) {
                    $mailer = new MailerService();
                    $mailer->sendProfessionalPendingEmail($email, $nome, $tipo);
                }
            }

            return [
                'id' => $userId,
                'nome' => $nome,
                'email' => $email,
                'tipo' => $tipo,
                'is_professional' => $isProfessional,
                'success_message' => $isProfessional
                    ? 'Cadastro recebido. Seu acesso profissional aguardará aprovação interna.'
                    : 'conta_criada'
            ];

        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new ValidationException('E-mail ou CPF já cadastrado.');
            }

            if ($e->getCode() === '42S22') {
                throw new ValidationException('Banco de dados desatualizado. Importe um dos SQLs consolidados em database/.');
            }

            throw $e;
        }
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function passwordHashAlgorithm()
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    }

    private function passwordHashOptions(): array
    {
        $options = [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 2,
        ];

        if (defined('PASSWORD_ARGON2ID') && password_algos() && in_array('argon2id', password_algos(), true)) {
            return $options;
        }

        return [];
    }

    private function hashUserPassword(string $plainPassword): string
    {
        return password_hash($plainPassword, $this->passwordHashAlgorithm(), $this->passwordHashOptions());
    }

    public function isValidCpf(?string $cpf): bool
    {
        if ($cpf === null) {
            return false;
        }

        $cpf = preg_replace('/\D+/', '', $cpf);
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }

        return true;
    }

    private function cpfExists(string $cpf, ?int $exceptUserId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE cpf = ?';
        $params = [$cpf];
        if ($exceptUserId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptUserId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function passwordValidationError(string $password): ?string
    {
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            return 'A senha deve conter no mínimo ' . self::MIN_PASSWORD_LENGTH . ' caracteres.';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return 'A senha deve conter pelo menos uma letra maiúscula.';
        }

        if (!preg_match('/[a-z]/', $password)) {
            return 'A senha deve conter pelo menos uma letra minúscula.';
        }

        if (!preg_match('/[0-9]/', $password)) {
            return 'A senha deve conter pelo menos um número.';
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return 'A senha deve conter pelo menos um caractere especial.';
        }

        return null;
    }

    private function ageValidationError(string $birthDate, bool $confirmed): ?string
    {
        if (trim($birthDate) === '') {
            return 'Data de nascimento é obrigatória.';
        }

        $timestamp = strtotime($birthDate);
        if ($timestamp === false) {
            return 'Data de nascimento inválida.';
        }

        $age = (int) date('Y') - (int) date('Y', $timestamp);
        if (date('md') < date('md', $timestamp)) {
            $age--;
        }

        if ($age < 18 && !$confirmed) {
            return 'Menores de 18 anos precisam de confirmação de maioridade ou assistência legal.';
        }

        return null;
    }

    private function pendingOfficeInviteRequirement(): ?array
    {
        if (!class_exists('App\Services\OrganizationInviteService')) {
            return null;
        }
        $inviteService = new OrganizationInviteService($this->pdo);
        try {
            return $inviteService->pendingOfficeInviteRequirement();
        } catch (\Throwable) {
            return null;
        }
    }

    private function logOabValidation(
        int $professionalId,
        string $action,
        ?string $previousStatus,
        string $newStatus,
        string $origin,
        ?string $message
    ): void {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO cna_validacao_logs (profissional_id, acao, status_anterior, status_novo, origem, mensagem)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$professionalId, $action, $previousStatus, $newStatus, $origin, $message]);
        } catch (PDOException $e) {
            error_log('OAB validation log error: ' . $e->getMessage());
        }
    }
}
