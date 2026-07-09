/**
 * Deploy & Packaging Script for WordPress Plugin.
 * Automates version bumping, readme.txt changelog updates, asset compilation, and ZIP packaging.
 */

const fs = require('fs');
const path = require('path');
const readline = require('readline');
const { execSync } = require('child_process');

const rl = readline.createInterface({
  input: process.stdin,
  output: process.stdout
});

const PLUGIN_SLUG = 'curs-bnr-convertor-valutar';
const ROOT_DIR = path.resolve(__dirname, '..');
const PHP_FILE_PATH = path.join(ROOT_DIR, `${PLUGIN_SLUG}.php`);
const README_FILE_PATH = path.join(ROOT_DIR, 'readme.txt');
const DIST_DIR = path.join(ROOT_DIR, 'dist');

// Utility to ask questions using async/await
function askQuestion(query) {
  return new Promise((resolve) => rl.question(query, resolve));
}

// Ensure directory exists recursively
function copyDirSync(src, dest) {
  fs.mkdirSync(dest, { recursive: true });
  const entries = fs.readdirSync(src, { withFileTypes: true });

  for (let entry of entries) {
    const srcPath = path.join(src, entry.name);
    const destPath = path.join(dest, entry.name);

    if (entry.isDirectory()) {
      // Do not skip block src directory to bundle uncompiled human-readable code in ZIP
      copyDirSync(srcPath, destPath);
    } else {
      fs.copyFileSync(srcPath, destPath);
    }
  }
}

async function run() {
  console.log('=== Pornire script deploy ===\n');

  try {
    // 1. Read current version from PHP file
    if (!fs.existsSync(PHP_FILE_PATH)) {
      throw new Error(`Fișierul principal PHP nu a fost găsit la: ${PHP_FILE_PATH}`);
    }
    
    let phpContent = fs.readFileSync(PHP_FILE_PATH, 'utf8');
    const versionHeaderMatch = phpContent.match(/Version:\s*([0-9.]+)/i);
    const currentVersion = versionHeaderMatch ? versionHeaderMatch[1] : '1.0.0';
    console.log(`Versiunea curentă: ${currentVersion}`);

    // 2. Prompt for new version
    const newVersionInput = await askQuestion(`Introdu versiunea nouă (lasă gol pentru ${currentVersion}): `);
    const newVersion = newVersionInput.trim() || currentVersion;

    let changelogText = '';
    
    if (newVersion !== currentVersion) {
      console.log(`Bumping version from ${currentVersion} to ${newVersion}...`);
      
      // Prompt for changelog entry
      changelogText = await askQuestion(`Introdu comentariile pentru changelog-ul versiunii ${newVersion}: `);
      changelogText = changelogText.trim();
      if (!changelogText) {
        changelogText = 'Actualizări și optimizări diverse.';
      }

      // Update PHP file headers and constants
      phpContent = phpContent.replace(
        /Version:\s*([0-9.]+)/i,
        `Version:           ${newVersion}`
      );
      phpContent = phpContent.replace(
        /define\(\s*'ONUEXRA_VERSION',\s*'([0-9.]+)'\s*\);/i,
        `define( 'ONUEXRA_VERSION', '${newVersion}' );`
      );
      fs.writeFileSync(PHP_FILE_PATH, phpContent, 'utf8');
      console.log('✓ Versiunea a fost actualizată în fișierul PHP.');

      // Update readme.txt Stable tag
      if (fs.existsSync(README_FILE_PATH)) {
        let readmeContent = fs.readFileSync(README_FILE_PATH, 'utf8');
        readmeContent = readmeContent.replace(
          /Stable tag:\s*([0-9.]+)/i,
          `Stable tag: ${newVersion}`
        );

        // Update readme.txt changelog section
        // We find the "= Changelog =" or "== Changelog ==" section and append the new version at the beginning
        const changelogSectionRegex = /(== Changelog ==|= Changelog =)/i;
        if (changelogSectionRegex.test(readmeContent)) {
          const newChangelogEntry = `\n\n= ${newVersion} =\n* ${changelogText}`;
          // Insert after the section header
          readmeContent = readmeContent.replace(changelogSectionRegex, `$1${newChangelogEntry}`);
        }
        
        fs.writeFileSync(README_FILE_PATH, readmeContent, 'utf8');
        console.log('✓ Versiunea și changelog-ul au fost actualizate în readme.txt.');
      }
    }

    // 3. Compile assets
    console.log('\nCompilare elemente bloc Gutenberg...');
    execSync('npm run build', { cwd: ROOT_DIR, stdio: 'inherit' });
    console.log('✓ Compilarea s-a încheiat cu succes.');

    // 4. Create zip archive
    console.log('\nPregătire structură fișiere pentru arhivă...');
    
    // Ensure clean dist directory
    if (fs.existsSync(DIST_DIR)) {
      fs.rmSync(DIST_DIR, { recursive: true, force: true });
    }
    fs.mkdirSync(DIST_DIR);

    const tempPluginDir = path.join(DIST_DIR, PLUGIN_SLUG);
    fs.mkdirSync(tempPluginDir);

    // List of files and folders to include
    const includes = [
      'assets',
      'blocks',
      'build',
      'includes',
      'languages',
      `${PLUGIN_SLUG}.php`,
      'readme.txt',
      'uninstall.php',
      'screenshot-1.png',
      'screenshot-2.png',
      'screenshot-3.png',
      'screenshot-4.png'
    ];

    for (const item of includes) {
      const srcPath = path.join(ROOT_DIR, item);
      const destPath = path.join(tempPluginDir, item);
      
      if (fs.existsSync(srcPath)) {
        const stats = fs.statSync(srcPath);
        if (stats.isDirectory()) {
          copyDirSync(srcPath, destPath);
        } else {
          fs.copyFileSync(srcPath, destPath);
        }
      }
    }

    console.log('✓ Fișierele au fost copiate în directorul temporar.');

    // Zip execution using native macOS zip command
    console.log('Creare arhivă ZIP...');
    const zipName = `${PLUGIN_SLUG}-v${newVersion}.zip`;
    const zipDest = path.join(DIST_DIR, zipName);
    
    // Run zip command relative to the dist directory to avoid path leakage in zip structures
    execSync(`zip -r "${zipName}" "${PLUGIN_SLUG}"`, { cwd: DIST_DIR, stdio: 'inherit' });
    
    // Clean up temporary folder
    fs.rmSync(tempPluginDir, { recursive: true, force: true });

    console.log(`\n========================================`);
    console.log(`✓ PROCES FINALIZAT CU SUCCES!`);
    console.log(`Arhiva generată: dist/${zipName}`);
    console.log(`========================================`);

  } catch (error) {
    console.error('\n❌ Eroare la rularea deploy-ului:', error.message);
  } finally {
    rl.close();
  }
}

run();
