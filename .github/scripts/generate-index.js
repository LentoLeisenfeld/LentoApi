const fs = require('fs');
const path = require('path');
const matter = require('gray-matter');
const glob = require('glob');

const files = glob.sync('docs/**/*.md');

const docs = files.map(file => {
  const content = fs.readFileSync(file, 'utf8');
  const parsed = matter(content, {
    engines: {
      json: s => JSON.parse(s)
    }
  });
  const slug = file
    .replace(/^docs\//, '')   // remove leading 'docs/'
    .replace(/\.md$/, '')     // remove '.md'
    .replace(/\\/g, '/');     // fix any Windows backslashes
  return {
    slug,
    title: parsed.data.title || slug,
    formattedTitle: parsed.data.formattedTitle || slug,
    tags: parsed.data.tags || [],
    excerpt: parsed.content.substr(0, 150),
    //content: parsed.content
  };
});

fs.writeFileSync('index.json', JSON.stringify(docs, null, 2));
console.log(`index.json mit ${docs.length} Einträgen erstellt.`);