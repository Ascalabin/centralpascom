========================================================================
PASCOM — PARÓQUIA SÃO PEDRO (ITAPETIM - PE)
Plugin Local para OBS Studio + Site no GitHub Pages (Sem Firebase)
========================================================================

O pacote contém duas pastas:

1) site-github-pages/
   Envie TODOS os arquivos dessa pasta para a raiz do seu repositório no
   GitHub (incluindo index.html, obs-config.json, .nojekyll, 404.html e
   a pasta assets/overlays/).
   Depois ative em: Settings -> Pages -> Deploy from a branch (main / root).

2) plugin-obs/
   Plugin/Script nativo para instalar localmente no OBS Studio:
   - pascom_obs_github.py  (Script oficial do OBS Studio em Python padrão)
   - overlay-local.html    (Overlay 100% offline/local do GC no OBS)
   - instalar-no-obs.bat   (Instalador automático para Windows)

------------------------------------------------------------------------
COMO INSTALAR O PLUGIN NO OBS STUDIO (PASSO A PASSO)
------------------------------------------------------------------------
1. No Windows, execute "plugin-obs\instalar-no-obs.bat" (ou copie os
   arquivos pascom_obs_github.py e overlay-local.html para uma pasta
   fixa no seu computador).
   * Requisito: Python 3.8+ instalado (https://www.python.org/downloads/).

2. Abra o OBS Studio e vá em:
   Ferramentas -> Scripts

3. Na aba "Configurações do Python", selecione a pasta onde o Python 3
   está instalado (o instalador .bat mostra o caminho exato).

4. Na aba "Scripts", clique em "+" e selecione:
   pascom_obs_github.py

5. Nas opções do script que aparecem à direita:
   - Cole a "URL do Site no GitHub Pages"
     (ex.: https://seu-usuario.github.io/pascom-sao-pedro/)
   - Clique em "Criar / Atualizar Fonte na Cena Atual"
     -> O plugin cria automaticamente a fonte "PASCOM GC - Overlay"
        (1920x1080, fundo transparente) na sua cena!

------------------------------------------------------------------------
COMO FUNCIONA NO DIA A DIA
------------------------------------------------------------------------
- Leitura direta do GitHub:
  O plugin consulta periodicamente o arquivo "obs-config.json" do seu
  site no GitHub Pages (cor litúrgica, animações, escala, posição,
  padres e cartões). Para atualizar as configurações salvas no GitHub,
  clique em "Baixar obs-config.json" na aba Transmissão do site e suba
  o arquivo na raiz do repositório.

- Controle ao vivo instantâneo (<20ms, sem Firebase):
  Quando você abre o seu site do GitHub Pages no navegador do computador
  da transmissão (ou como Painel Personalizado dentro do OBS), o site
  detecta o plugin automaticamente ("Plugin OBS Conectado") e envia os
  cliques de "COLOCAR NO AR" / "TIRAR DO AR" em tempo real.

- Teclas de Atalho no OBS Studio:
  Em Configurações -> Teclas de Atalho, procure por "PASCOM" para
  configurar atalhos no teclado ou Stream Deck:
  * PASCOM: Alternar GC No Ar / Fora do Ar
  * PASCOM: Próximo Cartão da Fila
  * PASCOM: Cartão Anterior da Fila
  * PASCOM: Sincronizar Configurações do GitHub
