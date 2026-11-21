// Synchronise head / header / footer / scripts des pages statiques depuis partials/.
// Usage : node tools/sync-chrome.mjs        (zéro dépendance)
import { readFileSync, writeFileSync, existsSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const read = (f) => readFileSync(join(root, f), "utf8");
const v = read("VERSION").trim();
const parts = {
  head: read("partials/head.html"),
  header: read("partials/header.html"),
  footer: read("partials/footer.html"),
  scripts: read("partials/scripts.html"),
};
// page → préfixe relatif vers la racine
const pages = {
  "index.html": "", "about.html": "", "ecosystem.html": "", "speaking.html": "", "contact.html": "", "404.html": "/",
  "nou-pa-nan-bel-diskou-anko/index.html": "../",
};
const sub = (t, b) => t.replaceAll("{{b}}", b).replaceAll("{{v}}", v).trimEnd();
const wrap = (name, body) => `<!--chrome:${name}-->\n${body}\n<!--/chrome:${name}-->`;
const re = (name) => new RegExp(`<!--chrome:${name}-->[\\s\\S]*?<!--/chrome:${name}-->`);

for (const [file, b] of Object.entries(pages)) {
  if (!existsSync(join(root, file))) continue;
  let h = read(file);
  const put = (name, fallbackRe, ins) => {
    const block = wrap(name, sub(parts[name], b));
    if (re(name).test(h)) h = h.replace(re(name), () => block);
    else if (fallbackRe && fallbackRe.test(h)) h = h.replace(fallbackRe, () => block);
    else if (ins) h = ins(h, block);
  };
  // retirer anciennes inclusions
  h = h.replace(/\s*<link rel="stylesheet" href="(\.\.\/)?assets\/css\/main\.css[^"]*" \/>/g, "");
  h = h.replace(/\s*<script src="(\.\.\/)?assets\/js\/main\.js[^"]*"><\/script>/g, "");
  put("head", null, (x, blk) => x.replace("</head>", `${blk}\n</head>`));
  put("header", /<header class="site-header">[\s\S]*?<\/header>/, null);
  put("footer", /<footer class="site-footer">[\s\S]*?<\/footer>/, null);
  put("scripts", null, (x, blk) => x.replace("</body>", `${blk}\n</body>`));
  // <main id="main"> autour du contenu
  if (!h.includes('<main id="main">')) {
    h = h.replace("<!--/chrome:header-->", '<!--/chrome:header-->\n<main id="main">');
    h = h.replace("<!--chrome:footer-->", "</main>\n<!--chrome:footer-->");
  }
  writeFileSync(join(root, file), h);
  console.log("synced", file);
}
