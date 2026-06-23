# Configuração pendente do ClamAV

O JusTraduz já suporta ClamAV via `CLAMAV_BINARY`, mas a instalação ainda depende do ambiente real. Para apresentação local, ele é opcional. Para produção com uploads reais de usuários, ele deve ser configurado e testado.

## Falta fazer em Linux/VPS

```bash
sudo apt update
sudo apt install clamav clamav-daemon
sudo systemctl stop clamav-freshclam || true
sudo freshclam
sudo systemctl enable --now clamav-freshclam
which clamscan
clamscan --version
```

Depois preencher no `backend/.env` real:

```env
CLAMAV_BINARY=/usr/bin/clamscan
CLAMAV_TIMEOUT_SECONDS=15
```

## Falta fazer em Windows/XAMPP

Instalar o ClamAV para Windows e confirmar o caminho:

```powershell
where.exe clamscan
clamscan --version
```

Exemplo para `backend/.env` local:

```env
CLAMAV_BINARY=C:\Program Files\ClamAV\clamscan.exe
CLAMAV_TIMEOUT_SECONDS=15
```

## Validação pendente

```powershell
php backend\tests\run.php
php scripts\check-production-readiness.php --env=backend/.env
```

Também falta testar upload válido e upload bloqueado depois da instalação.
