// Uso: cd docs/tools && npm install && node gerar-pdf.mjs
// Opcional: BROWSER_PATH=<caminho do Chrome/Chromium/Edge> para usar outro navegador.
import { readFileSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { Marked } from 'marked';
import puppeteer from 'puppeteer-core';
import { browserPath } from './navegador.mjs';

const here = dirname(fileURLToPath(import.meta.url));
const docsDir = resolve(here, '..');
const mdPath = join(docsDir, 'DOCUMENTACAO_VISE.md');
// Fica dentro do repositório porque o Chromium do snap não enxerga o /tmp do sistema.
const htmlPath = join(here, '.documentacao.html');
const pdfPath = join(docsDir, 'DOCUMENTACAO_VISE.pdf');

const pubspec = readFileSync(resolve(docsDir, '..', 'mobile', 'pubspec.yaml'), 'utf8');
const [versao, build] = (pubspec.match(/^version:\s*([^\s]+)/m)?.[1] ?? '1.0.0+1').split('+');

// Mesmo algoritmo de âncoras do GitHub, para os links do sumário funcionarem.
const slugify = (text) =>
  text
    .toLowerCase()
    .replace(/<[^>]+>/g, '')
    .replace(/[^\p{L}\p{N}\s_-]/gu, '')
    .trim()
    .replace(/\s/g, '-');

const marked = new Marked({ gfm: true });
marked.use({
  renderer: {
    heading({ tokens, depth, text }) {
      const html = this.parser.parseInline(tokens);
      return `<h${depth} id="${slugify(text)}">${html}</h${depth}>\n`;
    },
    listitem(item) {
      const body = this.parser.parse(item.tokens, !!item.loose);
      if (item.task) {
        const box = item.checked ? '☑' : '☐';
        return `<li class="task"><span class="box">${box}</span>${body.replace(/<input[^>]*>\s*/, '')}</li>\n`;
      }
      return `<li>${body}</li>\n`;
    },
  },
});

const body = marked.parse(readFileSync(mdPath, 'utf8'));
const hoje = new Date().toLocaleDateString('pt-BR');

const css = `
  @page { size: A4; margin: 18mm 16mm 20mm 16mm; }
  :root { --mint: #2FA06A; --mint-soft: #e6f6ee; --deep: #0E1A20; --petrol: #16252D;
          --ink: #1b2a31; --muted: #5a6c74; --line: #d6e2e6; }
  * { box-sizing: border-box; }
  html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  body { font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif; color: var(--ink);
         font-size: 10.5pt; line-height: 1.5; margin: 0; }
  .cover { height: 255mm; display: flex; flex-direction: column; justify-content: center;
           page-break-after: always; border-left: 10px solid var(--mint); padding-left: 18mm; }
  .cover img { width: 46mm; height: 46mm; object-fit: cover; border-radius: 9mm; margin-bottom: 10mm;
               box-shadow: 0 3px 12px rgba(14, 26, 32, .25); }
  .cover .tag { color: var(--mint); font-weight: 700; letter-spacing: .2em; text-transform: uppercase; font-size: 11pt; }
  .cover h1 { font-size: 38pt; color: var(--deep); margin: 6mm 0 2mm; border: 0; padding: 0; }
  .cover .sub { font-size: 15pt; color: var(--muted); margin-bottom: 14mm; }
  .cover dl { display: grid; grid-template-columns: 45mm auto; gap: 2mm 6mm; font-size: 11pt; margin: 0; }
  .cover dt { color: var(--muted); }
  .cover dd { margin: 0; font-weight: 600; }
  h1, h2, h3, h4 { color: var(--deep); line-height: 1.25; page-break-after: avoid; break-after: avoid; }
  h1 { font-size: 22pt; margin: 0 0 4mm; }
  h2 { font-size: 16pt; border-bottom: 3px solid var(--mint); padding-bottom: 2mm; margin-top: 0;
       page-break-before: always; break-before: page; }
  h3 { font-size: 12.5pt; margin-top: 7mm; color: #1E7A50; }
  h4 { font-size: 11pt; margin-top: 5mm; color: var(--ink); }
  body > h1:first-of-type, body > h1:first-of-type + p, body > h1:first-of-type + p + table,
  body > h1:first-of-type + p + table + hr { display: none; }
  #sumário { page-break-before: avoid; break-before: auto; }
  a { color: #1E7A50; text-decoration: none; }
  p, li { orphans: 3; widows: 3; }
  hr { border: 0; border-top: 1px solid var(--line); margin: 6mm 0; }
  hr + h2 { margin-top: 0; }
  table { width: 100%; border-collapse: collapse; margin: 3mm 0 5mm; font-size: 9.3pt; page-break-inside: auto; }
  tr { page-break-inside: avoid; break-inside: avoid; }
  thead { display: table-header-group; }
  th { background: var(--petrol); color: #fff; text-align: left; padding: 2mm 2.5mm; font-weight: 600; }
  td { padding: 1.6mm 2.5mm; border-bottom: 1px solid var(--line); vertical-align: top; }
  tbody tr:nth-child(even) td { background: #f3f8f6; }
  code { font-family: 'Cascadia Mono', 'DejaVu Sans Mono', Consolas, 'Courier New', monospace; font-size: 8.8pt;
         background: #edf3f1; padding: .3mm 1.2mm; border-radius: 3px; }
  pre { background: var(--deep); color: #e6f0ee; padding: 4mm; border-radius: 6px; overflow: hidden;
        white-space: pre-wrap; word-break: break-word; page-break-inside: avoid; break-inside: avoid;
        font-size: 8.2pt; line-height: 1.35; }
  pre code { background: none; padding: 0; color: inherit; font-size: inherit;
             font-family: 'DejaVu Sans Mono', 'Cascadia Mono', Consolas, monospace; }
  blockquote { margin: 4mm 0; padding: 2.5mm 4mm; border-left: 4px solid var(--mint);
               background: var(--mint-soft); color: #174a33; border-radius: 0 6px 6px 0; }
  blockquote p { margin: 0; }
  ul, ol { padding-left: 6mm; }
  li.task { list-style: none; margin-left: -5mm; }
  li.task .box { color: var(--mint); margin-right: 2mm; }
  p[align="center"] { text-align: center; page-break-inside: avoid; break-inside: avoid; }
  p[align="center"] img { width: 40mm; height: auto; margin: 0 1.5mm; border-radius: 10px;
                          box-shadow: 0 2px 8px rgba(14, 26, 32, .3); }
  p[align="center"] img:only-child { width: 52mm; }
  p.largo img, p.largo img:only-child { width: 100%; border-radius: 6px; margin: 0; }
  p[align="center"] em { color: var(--muted); font-size: 9pt; }
`;

const html = `<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<base href="${pathToFileURL(docsDir).href}/">
<title>Vise Saúde Mental — Documentação do Sistema</title>
<style>${css}</style>
</head>
<body>
<section class="cover">
  <img src="logo.jpeg" alt="VISE-MT">
  <div class="tag">Saúde mental na escola · Mato Grosso</div>
  <h1>Vise Saúde Mental</h1>
  <div class="sub">Documentação Técnica e Funcional do Sistema</div>
  <dl>
    <dt>Versão do app</dt><dd>${versao} (build ${build ?? '1'})</dd>
    <dt>Backend</dt><dd>PHP 8 + MySQL (API e painel web)</dd>
    <dt>Stack</dt><dd>Flutter · PHP · MySQL · Shorebird</dd>
    <dt>Produção</dt><dd>visemt.com.br/api</dd>
    <dt>Gerado em</dt><dd>${hoje}</dd>
  </dl>
</section>
${body}
</body>
</html>`;

writeFileSync(htmlPath, html, 'utf8');

const browser = await puppeteer.launch({
  executablePath: browserPath(),
  headless: true,
  args: ['--no-sandbox'],
});
try {
  const page = await browser.newPage();
  await page.goto(pathToFileURL(htmlPath).href, { waitUntil: 'load' });
  await page.pdf({
    path: pdfPath,
    format: 'A4',
    printBackground: true,
    preferCSSPageSize: true,
    displayHeaderFooter: true,
    headerTemplate: '<div></div>',
    footerTemplate: `
      <div style="width:100%;font-family:Segoe UI,Arial,sans-serif;font-size:8px;color:#5a6c74;
                  padding:0 16mm;display:flex;justify-content:space-between;">
        <span>Vise Saúde Mental — Documentação do Sistema</span>
        <span>Página <span class="pageNumber"></span> de <span class="totalPages"></span></span>
      </div>`,
  });
} finally {
  await browser.close();
  rmSync(htmlPath, { force: true });
}

console.log(`PDF gerado: ${pdfPath}`);
