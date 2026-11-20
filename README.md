# PASCOM — Paróquia São Pedro (Itapetim - PE) — GitHub Pages

Site estático da PASCOM pronto para publicar no **GitHub Pages**, contendo:
- **Escala Dominical** (calendário, missas 09h/19h, missas extras, cadastro de agentes, sorteio, senha padrão `pascom2026` e cópia para WhatsApp)
- **Pendências de Artes** (pedidos, prazos, filtros, anexo de arte final com compressão automática e download)
- **Sobreposição Litúrgica** (5 molduras oficiais 1080×1920, Liturgia Diária automática, exportação 9:16 e 4:5)
- **Nuvem / Backup JSON** (exportar/importar backup ou sincronizar opcionalmente com Firebase Realtime Database gratuito)

## Como publicar no GitHub Pages
1. Crie um repositório público no GitHub e envie todos os arquivos desta pasta para a raiz (incluindo `index.html`, `.nojekyll`, `404.html` e `assets/overlays/`).
2. No repositório, acesse **Settings → Pages**.
3. Em **Build and deployment**, selecione **Deploy from a branch**, branch `main` e pasta `/ (root)`, depois clique em **Save**.
4. Aguarde ~1 minuto e acesse a URL gerada (`https://seu-usuario.github.io/seu-repositorio/`).
