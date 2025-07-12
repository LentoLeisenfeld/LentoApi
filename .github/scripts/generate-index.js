const fs = require('fs');
const path = require('path');
const matter = require('gray-matter');
const glob = require('glob');

// ALLE .md-Dateien im Repo finden (Rekursiv)
const files = glob.sync('**/*.md', { ignore: 'node_modules/**' });

const docs = files.map(file => {
  const content = fs.readFileSync(file, 'utf8');
  const parsed = matter(content, { engines: {
    // JSON Frontmatter support
    json: s => JSON.parse(s)
  }});
  const slug = file.replace(/\.md$/, '').replace(/\\/g, '/');
  return {
    slug,
    title: parsed.data.title || slug,
    tags: parsed.data.tags || [],
    excerpt: parsed.content.substr(0, 150),
    content: parsed.content
  };
});

fs.writeFileSync('search-index.json', JSON.stringify(docs, null, 2));
console.log(`search-index.json mit ${docs.length} Einträgen erstellt.`);