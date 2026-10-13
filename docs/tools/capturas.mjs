// Gera as capturas de tela usadas na documentação (docs/img/*.png).
// Precisa de um backend com os dados demo (php bin/install.php --seed --seed-respostas=80)
// e do app web compilado apontando para ele:
//   flutter build web --release --no-web-resources-cdn --dart-define=API_BASE_URL=<ADMIN_URL>/api/v1
// Uso: ADMIN_URL=http://127.0.0.1:8000 APP_URL=http://127.0.0.1:8081 node capturas.mjs
import { existsSync, mkdirSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import puppeteer from 'puppeteer-core';
import { browserPath } from './navegador.mjs';

const here = dirname(fileURLToPath(import.meta.url));
const imgDir = resolve(here, '..', 'img');
mkdirSync(imgDir, { recursive: true });

const ADMIN = (process.env.ADMIN_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const APP = (process.env.APP_URL || 'http://127.0.0.1:8081').replace(/\/$/, '');
const SENHA = 'password';
// O calendário do app já abre em 21/10/2009, a data do aluno demo.
const ALUNO_CPF = '07593256189';

const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

const browser = await puppeteer.launch({
  executablePath: browserPath(),
  headless: true,
  args: ['--no-sandbox', '--enable-unsafe-swiftshader'],
});

async function salvar(page, nome) {
  const path = join(imgDir, `${nome}.png`);
  await page.screenshot({ path });
  console.log(`  ${nome}.png`);
}

// ------------------------------------------------------------------ painel web

async function painel() {
  console.log('Painel web');
  const ctx = await browser.createBrowserContext();
  const page = await ctx.newPage();
  await page.setViewport({ width: 1366, height: 860, deviceScaleFactor: 1.5 });

  await page.goto(`${ADMIN}/admin/login`, { waitUntil: 'networkidle0' });
  await salvar(page, 'web-login');
  await page.type('input[name=email]', 'admin@vise.local');
  await page.type('input[name=password]', SENHA);
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit], input[type=submit]')]);

  const abrir = async (rota, nome) => {
    await page.goto(`${ADMIN}${rota}`, { waitUntil: 'networkidle0' });
    await salvar(page, nome);
  };
  await abrir('/admin', 'web-dashboard');
  await abrir('/admin/relatorios', 'web-relatorios');
  await abrir('/admin/importar?tipo=alunos', 'web-importar');
  await abrir('/admin/turmas', 'web-turmas');
  await abrir('/admin/alunos', 'web-alunos');

  const questionario = await page.evaluate(async (base) => {
    const html = await (await fetch(`${base}/admin/questionarios`)).text();
    return html.match(/\/admin\/questionarios\/(\d+)"/)?.[1] ?? null;
  }, ADMIN);
  if (questionario) {
    await abrir(`/admin/questionarios/${questionario}`, 'web-questionario');
    await abrir(`/admin/perguntas/create?questionario_id=${questionario}`, 'web-nova-pergunta');
  }
  await ctx.close();
}

// ------------------------------------------------------------------ app (Flutter web)

async function abrirApp(ctx) {
  const page = await ctx.newPage();
  await page.setViewport({ width: 412, height: 860, deviceScaleFactor: 2 });
  await page.goto(`${APP}/`, { waitUntil: 'networkidle0', timeout: 90000 });
  await esperar(3000);
  await page.evaluate(() => document.querySelector('flt-semantics-placeholder')?.click());
  await esperar(800);
  return page;
}

/**
 * Toca no menor elemento de acessibilidade cujo texto contém `texto`.
 * `fx`/`fy` escolhem o ponto dentro dele (0–1), para botões fundidos num cartão.
 */
async function tocar(page, texto, { tentativas = 20, fx = 0.5, fy = 0.5 } = {}) {
  for (let i = 0; i < tentativas; i++) {
    const alvo = await page.evaluate((t, fx, fy) => {
      const candidatos = [...document.querySelectorAll('flt-semantics, input, textarea, [role]')]
        .map((e) => ({ e, r: e.getBoundingClientRect() }))
        .map((c) => ({ ...c, rotulo: (c.e.getAttribute('aria-label') || c.e.textContent || '').trim() }))
        .filter(({ r, rotulo }) => r.width > 0 && r.height > 0 && rotulo.includes(t))
        .sort((a, b) => (a.rotulo === t ? 0 : 1) - (b.rotulo === t ? 0 : 1) || a.r.width * a.r.height - b.r.width * b.r.height);
      const r = candidatos[0]?.r;
      return r ? { x: r.x + r.width * fx, y: r.y + r.height * fy } : null;
    }, texto, fx, fy);
    if (alvo) {
      await page.mouse.click(alvo.x, alvo.y);
      await esperar(900);
      return;
    }
    await esperar(500);
  }
  const visiveis = await page.evaluate(() =>
    [...document.querySelectorAll('flt-semantics')]
      .map((e) => `${e.getAttribute('role') ?? '-'}: ${(e.getAttribute('aria-label') ?? e.textContent ?? '').replace(/\s+/g, ' ').slice(0, 60)}`)
      .join('\n')
  );
  throw new Error(`Elemento "${texto}" não encontrado na tela do app. Na tela:\n${visiveis}`);
}

async function digitar(page, campo, valor) {
  await tocar(page, campo);
  await page.keyboard.type(valor, { delay: 30 });
  await esperar(300);
}

async function appAluno() {
  console.log('App do aluno');
  const ctx = await browser.createBrowserContext();
  const page = await abrirApp(ctx);
  await salvar(page, 'app-entrada');

  await tocar(page, 'Sou aluno');
  await digitar(page, 'CPF', ALUNO_CPF);
  await tocar(page, 'Data de nascimento');
  await tocar(page, 'OK');
  await salvar(page, 'app-login-aluno');
  await tocar(page, 'Começar');
  await esperar(3500);

  await salvar(page, 'app-termo');
  await tocar(page, 'Li e aceito participar');
  await tocar(page, 'Continuar');
  await esperar(2500);

  await salvar(page, 'app-questionarios');
  await tocar(page, 'Bem-estar emocional', { fx: 0.8, fy: 0.78 });
  await esperar(2000);
  await salvar(page, 'app-avatar');
  await tocar(page, 'Explorar etapas');
  await esperar(2000);
  await salvar(page, 'app-caminho');
  await tocar(page, 'Toque para continuar');
  await esperar(2000);
  await salvar(page, 'app-pergunta');
  await ctx.close();
}

async function appEquipe() {
  console.log('App da equipe');
  const ctx = await browser.createBrowserContext();
  const page = await abrirApp(ctx);
  await tocar(page, 'Sou da equipe');
  await digitar(page, 'E-mail', 'admin@vise.local');
  await digitar(page, 'Senha', SENHA);
  await tocar(page, 'Entrar');
  await esperar(4000);
  await salvar(page, 'app-salas');

  await page.setViewport({ width: 1280, height: 820, deviceScaleFactor: 1.5 });
  await esperar(2000);
  await tocar(page, 'Painel');
  await esperar(4000);
  await salvar(page, 'app-painel-tablet');
  await ctx.close();
}

try {
  await painel();
  await appAluno();
  await appEquipe();
} finally {
  await browser.close();
}
console.log(`Capturas em ${imgDir}`);
if (!existsSync(join(imgDir, 'app-pergunta.png'))) process.exitCode = 1;
