# Configurar ClamAV

O JusTraduz ja usa `UploadScannerService` para bloquear assinaturas perigosas e, quando `CLAMAV_BINARY` esta configurado, tambem executa o ClamAV nos uploads.

Para apresentacao local, o ClamAV e opcional. Para producao com uploads reais de usuarios, ele e recomendado.

## Linux/VPS

Instale os pacotes:

```bash
sudo apt update
sudo apt install clamav clamav-daemon
sudo systemctl stop clamav-freshclam || true
sudo freshclam
sudo systemctl enable --now clamav-freshclam
```

Confirme o caminho:

```bash
which clamscan
clamscan --version
```

No `backend/.env` de producao:

```env
CLAMAV_BINARY=/usr/bin/clamscan
CLAMAV_TIMEOUT_SECONDS=15
```

## Windows/XAMPP

Instale o ClamAV para Windows pelo instalador oficial ou gerenciador de pacotes confiavel. Depois confirme o caminho do executavel:

```powershell
where.exe clamscan
clamscan --version
```

No `backend/.env` local, use o caminho encontrado. Exemplo:

```env
CLAMAV_BINARY=C:\Program Files\ClamAV\clamscan.exe
CLAMAV_TIMEOUT_SECONDS=15
```

## Validacao

Rode:

```powershell
php backend\tests\run.php
php scripts\check-production-readiness.php --env=backend/.env
```

Depois teste um upload valido pelo sistema. Se o ClamAV estiver mal configurado, o upload sera recusado para evitar aceitar arquivo sem varredura.

## Comportamento sem ClamAV

Se `CLAMAV_BINARY` ficar vazio, o sistema continua usando a heuristica interna:

- bloqueio de EICAR;
- bloqueio de scripts e PHP embutido;
- bloqueio de extensoes executaveis;
- validacao adicional de DOCX no fluxo de documentos e anexos.

Esse modo e suficiente para desenvolvimento e apresentacao, mas nao substitui antivirus em producao real.
