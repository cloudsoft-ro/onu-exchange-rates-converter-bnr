# Prompt detaliat: Plugin WordPress „Curs BNR – Convertor Valutar”

Folosește acest prompt ca specificație completă pentru un AI coding assistant (Claude Code, Cursor, etc.) care va genera pluginul.

**Nume plugin:** Curs BNR – Convertor Valutar
**Slug WordPress.org:** `curs-bnr-convertor-valutar`
**Text domain:** `curs-bnr-convertor-valutar`
**Prefix funcții/clase/DB:** `cbcv_` (Curs BNR Convertor Valutar) sau namespace `CBCV\`

*Nume/slug ales pentru a evita coliziunea cu plugin-urile existente pe WordPress.org (`curs-valutar-bnr`, `curs-valutar-live-bnr`, `convertor-valutar-curs-bnr`, `wp-cursbnr`) și pentru a acoperi cele două clustere de căutare principale: „curs bnr” și „convertor valutar”.*

---

## PROMPT

Creează un plugin WordPress complet, numit **„Curs BNR – Convertor Valutar”** (slug: `curs-bnr-convertor-valutar`), care respectă **100% WordPress.org Plugin Directory Guidelines** și **WordPress Coding Standards (WPCS)**. Pluginul aduce zilnic cursurile de schimb publicate de Banca Națională a României (BNR), le salvează în baza de date WordPress, permite conversii între valute, și oferă afișare prin shortcode, widget clasic, bloc Gutenberg și widget Elementor.

### 1. Sursa de date BNR

- Endpoint zilnic curent: `https://www.bnr.ro/nbrfxrates.xml`
- Endpoint istoric anual: `https://bnr.ro/files/xml/years/nbrfxrates{YEAR}.xml` (ex: `nbrfxrates2026.xml`)
- Format: XML cu namespace, structură `<DataSet><Body><Cube date="YYYY-MM-DD"><Rate currency="XXX" multiplier="100">valoare</Rate></Cube></Body></DataSet>`
- Atenție la atributul `multiplier` (unele valute precum HUF, JPY au multiplier 100) — valoarea reală = `Rate / multiplier`.
- Toate cursurile sunt exprimate față de RON (1 unitate valută = X RON).
- Folosește `WP_Http` / `wp_remote_get()` — **niciodată** `file_get_contents` sau cURL direct (cerință WPCS/securitate).
- Parsează XML cu `simplexml_load_string()` cu `libxml_disable_entity_loader` / flag `LIBXML_NONET` pentru a preveni XXE.
- Validează structura înainte de procesare; în caz de eroare, loghează și păstrează ultimul curs valid (nu suprascrie cu date invalide).

### 2. Fetch automat zilnic (cron)

- Folosește `wp_schedule_event()` cu hook custom `cbcv_daily_fetch`, programat zilnic la o oră configurabilă (implicit 13:15, ora la care BNR publică cursul — verifică ora reală de publicare).
- La activare plugin (`register_activation_hook`): programează cron-ul dacă nu există deja.
- La dezactivare (`register_deactivation_hook`): șterge cron-ul cu `wp_clear_scheduled_hook()`.
- Adaugă și un fallback manual: buton „Actualizează acum" în pagina de admin, cu verificare nonce.
- Nu suprascrie datele dacă BNR nu publică în weekend/sărbători (BNR nu publică sâmbăta/duminica) — verifică dacă data din XML e mai nouă decât ultima înregistrare salvată înainte de a insera.
- Implementează retry logic (max 3 tentative, backoff exponențial) în caz de eroare de rețea, folosind `WP_Error`.

### 3. Structură bază de date

Creează un tabel custom (nu folosi `postmeta`/`options` pentru date istorice, e ineficient):

```sql
CREATE TABLE {$wpdb->prefix}cbcv_exchange_rates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rate_date DATE NOT NULL,
    currency_code VARCHAR(3) NOT NULL,
    rate DECIMAL(15,6) NOT NULL,
    multiplier SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY rate_date_currency (rate_date, currency_code),
    KEY currency_code (currency_code),
    KEY rate_date (rate_date)
) {$charset_collate};
```

- Creează/actualizează tabelul folosind `dbDelta()` în `register_activation_hook`, cu versionare de schemă în `options` (`cbcv_db_version`) pentru upgrade-uri viitoare.
- La dezinstalare (`uninstall.php`, nu `register_uninstall_hook`), oferă o opțiune în setări „Șterge toate datele la dezinstalare" (implicit OFF, pentru a nu pierde date accidental) — respectă cerința WordPress.org de a curăța DB doar dacă utilizatorul o cere explicit.
- Adaugă un job de curățare opțional (configurabil, implicit dezactivat) pentru a păstra doar ultimele N zile de istoric, dacă utilizatorul vrea să limiteze dimensiunea tabelului.

### 4. Logica de conversie valutară

- Funcție centrală `cbcv_convert( float $amount, string $from_currency, string $to_currency, string $date = null )`.
- Suportă:
  - Conversie RON → valută
  - Conversie valută → RON
  - Conversie valută → valută (prin RON ca pivot: `amount / rate_from * rate_to`)
  - Conversie la o dată istorică specifică (folosind cea mai recentă înregistrare ≤ data cerută, dacă data exactă nu are curs publicat)
- Cache cu `wp_cache_get`/`wp_cache_set` (object cache) pentru cursul zilei curente, TTL 1 oră, pentru a evita interogări DB repetate.
- Returnează `WP_Error` pentru valute inexistente sau date fără curs disponibil.

### 5. Shortcode

`[cbcv_curs]` cu atribute:
- `valute="EUR,USD,GBP"` — listă valute afișate (implicit toate cele ~30 publicate de BNR)
- `data="YYYY-MM-DD"` — istoric (implicit azi)
- `stil="tabel|inline|card"`
- `arata_variatie="da|nu"` — afișează diferența față de ziua precedentă (↑/↓ colorat)

`[cbcv_convertor]` — afișează un formular interactiv de conversie (JS, fără reload), atribute:
- `de_la="EUR"` `catre="RON"` (valori implicite pre-selectate, editabile de vizitator)

*(Opțional: înregistrează și alias-uri „prietenoase" `[curs_bnr]` / `[convertor_valutar]` pentru UX mai bun în conținut, atâta timp cât nu se ciocnesc cu shortcode-uri din alte plugin-uri active — verifică la runtime cu `shortcode_exists()` înainte de a le înregistra.)*

### 6. Widget clasic WordPress (Widgets API / `WP_Widget`)

- Clasă `CBCV_Widget extends WP_Widget`.
- Configurabil din Aspect → Widgets: titlu, valute afișate, stil, afișare variație.
- Compatibil cu Legacy Widgets și Widgets Block Editor (widgets.php în modul bloc).

### 7. Bloc Gutenberg

- Bloc nativ înregistrat cu `register_block_type()`, folosind `block.json` (API version 3).
- Build cu `@wordpress/scripts` (`wp-scripts build`), respectând structura standard `src/index.js`, `src/edit.js`, `src/save.js` (sau server-side render cu `render_callback` pentru randare dinamică — recomandat, ca datele să fie mereu la zi fără rebuild).
- Panel InspectorControls în editor pentru: selecție valute (checkboxe), stil afișare, dată istorică, comutator variație.
- Preview live în editor (folosește `ServerSideRender` component pentru a reflecta shortcode-ul echivalent).
- Categorie bloc: „widgets", icon dedicat, `supports: { align: true }`.

### 8. Widget Elementor

- Verifică la runtime dacă Elementor e activ (`did_action( 'elementor/loaded' )`) înainte de a înregistra integrarea — **nu declara Elementor ca dependență obligatorie**.
- Clasă widget care extinde `\Elementor\Widget_Base`, înregistrată pe hook `elementor/widgets/register`.
- Controale Elementor: selecție valute (Select2 multiple), stil, tipografie/culori (folosind `Group_Control_Typography`, `Group_Control_Border`, etc. pentru integrare vizuală nativă cu tema Elementor).
- Categorie widget custom „Curs BNR" în panoul Elementor.

### 9. Panou de administrare

- Meniu în admin: **Setări → Curs BNR**.
- Secțiuni (folosind Settings API — `register_setting`, `add_settings_section`, `add_settings_field`):
  - Ora fetch zilnic
  - Valute active/afișate implicit
  - Format afișare (nr. decimale, separator)
  - Opțiune ștergere date la dezinstalare
  - Buton „Testează conexiunea BNR" + „Actualizează acum"
  - Tabel cu ultimele 10 fetch-uri (status, dată, erori) pentru debugging
- Toate formularele cu `wp_nonce_field()` + verificare `check_admin_referer()`.
- Toate input-urile sanitizate (`sanitize_text_field`, `absint`, etc.) și output-urile escapate (`esc_html`, `esc_attr`, `esc_url`).
- **Link de susținere („Buy me a coffee")**: adaugă un link discret către `https://buymeacoffee.com/constantinonu` în două locuri, fără a fi intrusiv sau a bloca funcționalitatea:
  - În sidebar-ul paginii de setări (widget-box „Susține dezvoltarea", cu `esc_url()` la link și `target="_blank" rel="noopener noreferrer"`).
  - Ca link „Support” în rândul plugin-ului din Plugins → Installed Plugins, folosind filtrul `plugin_row_meta` (alături de „Details”/„View support” — pattern standard folosit de majoritatea plugin-urilor de pe WordPress.org, deci acceptat de guidelines).
  - Nu folosește popup-uri, notificări admin recurente sau nag-screens pentru asta — WordPress.org interzice explicit „excessive/aggressive donation prompts”. Link static, o singură dată vizibil, e suficient.

### 10. Cerințe standard WordPress.org (obligatorii)

- **Prefixare**: toate funcțiile, clasele, hook-urile, opțiunile DB cu prefix unic `cbcv_` sau clasă în namespace `CBCV\`.
- **Internaționalizare**: tot textul vizibil prin `__()`, `_e()`, `esc_html__()` etc., text domain = slug-ul pluginului; include fișier `.pot` generat cu `wp-cli i18n make-pot`.
- **Header plugin standard** (`Plugin Name`, `Plugin URI`, `Description`, `Version`, `Requires at least`, `Requires PHP`, `Author`, `License: GPLv2 or later`, `Text Domain`).
- **`readme.txt`** în format WordPress.org standard (Stable tag, Tested up to, Requires PHP, secțiuni Description/Installation/FAQ/Screenshots/Changelog).
- **Securitate**: nonces la toate acțiunile care modifică date, capability checks (`current_user_can('manage_options')`) pentru pagini admin, escape la output, sanitize la input, prepared statements (`$wpdb->prepare()`) pentru toate query-urile SQL directe.
- **Fără hardcodare de credențiale/API keys** (nu e cazul aici, BNR e public, dar respectă principiul).
- **Fără apeluri externe fără consimțământ ascuns** — documentează clar în readme că pluginul contactează bnr.ro zilnic.
- **Compatibilitate**: `Requires at least: 6.0`, `Requires PHP: 7.4` (sau 8.0, în funcție de sintaxa folosită — evită sintaxă PHP 8-only dacă vrei compatibilitate largă).
- **Nu bloca activarea** dacă un serviciu extern (BNR) e temporar indisponibil — activarea trebuie să reușească mereu, fetch-ul se face asincron.
- **Uninstall clean**: `uninstall.php` separat, verifică `WP_UNINSTALL_PLUGIN` constant.
- **Fără tracking/analytics fără opt-in explicit.**
- Cod formatat conform **WordPress-Extra ruleset** (PHPCS + `wp-coding-standards/wpcs`), fără warning-uri PHPCS.

### 11. Structură de fișiere recomandată

```
curs-bnr-convertor-valutar/
├── curs-bnr-convertor-valutar.php   (fișier principal, header plugin)
├── uninstall.php
├── readme.txt
├── includes/
│   ├── class-cbcv-fetcher.php       (fetch + parsare XML)
│   ├── class-cbcv-db.php            (operații DB)
│   ├── class-cbcv-converter.php     (logica de conversie)
│   ├── class-cbcv-cron.php
│   ├── class-cbcv-shortcodes.php
│   ├── class-cbcv-widget.php
│   ├── class-cbcv-admin.php
│   └── class-cbcv-elementor.php
├── blocks/
│   └── curs-valutar/
│       ├── block.json
│       ├── src/ (index.js, edit.js, save.js, editor.scss)
│       └── build/
├── assets/
│   ├── css/
│   └── js/
├── languages/
│   └── curs-bnr-convertor-valutar.pot
└── tests/
    └── (PHPUnit, folosind WP_UnitTestCase, mock la wp_remote_get)
```

### 12. Testare

- PHPUnit cu `WP_UnitTestCase`, mock-uind `wp_remote_get` (nu apela BNR real în teste).
- Teste pentru: parsare XML validă/invalidă, conversie RON↔valută↔valută, comportament cron, sanitizare input shortcode.
- Testează manual pe PHP 7.4, 8.1, 8.3 și WP ultimele 3 versiuni majore.

### 12. `readme.txt` — conținut exact (SEO + GEO pentru WordPress.org)

Generează `readme.txt` cu acest conținut, respectând formatul standard WordPress.org (primele linii determină direct ce apare în rezultatele de căutare din directory și în cardurile citate de motoarele generative):

```
=== Curs BNR – Convertor Valutar ===
Contributors: (username-ul tău wordpress.org)
Donate link: https://buymeacoffee.com/constantinonu
Tags: curs bnr, curs valutar, convertor valutar, exchange rate, gutenberg block
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Curs valutar BNR actualizat automat zilnic, convertor între valute, widget, shortcode, bloc Gutenberg și widget Elementor.

== Description ==

**Curs BNR – Convertor Valutar** aduce automat, în fiecare zi, cursurile oficiale de schimb publicate de Banca Națională a României (BNR) și le afișează pe site-ul tău WordPress prin shortcode, widget clasic, bloc Gutenberg sau widget Elementor.

= Funcționalități =

* Fetch automat zilnic al cursului BNR (cron nativ WordPress)
* Istoric complet al cursurilor, salvat în baza de date
* Convertor valutar interactiv (orice pereche de valute, prin RON)
* Shortcode `[cbcv_curs]` pentru afișare curs
* Shortcode `[cbcv_convertor]` pentru convertor interactiv
* Widget clasic pentru sidebar/footer
* Bloc nativ Gutenberg, cu preview live în editor
* Widget dedicat pentru Elementor (dacă ai Elementor activ)
* Afișare variație zilnică (crescător/descrescător)
* 100% compatibil cu standardele WordPress.org (securitate, i18n, performanță)

= De ce Curs BNR – Convertor Valutar =

Spre diferență de alte plugin-uri similare, mai vechi și neîntreținute, acest plugin este construit pe arhitectură modernă (bloc Gutenberg cu server-side render, integrare Elementor, cache la nivel de obiect) și respectă integral ghidul de securitate și cod al WordPress.org.

== Installation ==

1. Încarcă folderul plugin-ului în `/wp-content/plugins/` sau instalează direct din Plugins → Add New, căutând „Curs BNR”.
2. Activează plugin-ul din meniul Plugins.
3. Accesează Setări → Curs BNR pentru a configura valutele afișate și ora de actualizare zilnică.
4. Folosește shortcode-ul `[cbcv_curs]`, widget-ul din Aspect → Widgets, blocul Gutenberg „Curs BNR” sau widget-ul Elementor.

== Frequently Asked Questions ==

= De unde vin datele despre curs? =
Direct de la sursa oficială BNR (bnr.ro), fără intermediari.

= Cât de des se actualizează cursul? =
O dată pe zi, automat, la ora configurată în setări (implicit 13:15, ora publicării BNR).

= Funcționează fără Elementor? =
Da. Widget-ul Elementor apare doar dacă ai Elementor activ; restul funcțiilor (shortcode, widget clasic, bloc Gutenberg) funcționează independent.

== Screenshots ==

1. Panou de setări
2. Shortcode afișat pe pagină
3. Bloc Gutenberg în editor
4. Widget Elementor

== Changelog ==

= 1.0.0 =
* Lansare inițială.
```

Notă privind **tags**: primele 5 tag-uri sunt cele mai importante pentru algoritmul de căutare WordPress.org — de aceea `curs bnr` e primul (volum de căutare cel mai mare), urmat de `curs valutar` și `convertor valutar`. Evită tag-uri irelevante sau înșirate excesiv (WordPress.org penalizează keyword-stuffing).

Notă privind **GEO** (citare de către motoare generative/AI): pe lângă readme.txt, creează și o pagină dedicată (ex. pe onu.ro sau un landing separat) cu markup `schema.org/SoftwareApplication` (nume, descriere, applicationCategory, operatingSystem: WordPress, offers/price: gratuit), plus un FAQ clar în text simplu — modelele generative extrag preferențial din structuri FAQ și schema markup, nu din prozǎ descriptivă lungă.

---

**Livrabil final**: cod complet, funcțional, comentat în engleză (convenție WordPress.org pentru cod), text vizibil pentru utilizator în română (via i18n, cu `.pot` disponibil pentru traduceri), pregătit pentru submit pe WordPress.org Plugin Directory.
