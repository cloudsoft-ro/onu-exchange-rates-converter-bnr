=== Onu Exchange Rates and Converter for BNR ===
Contributors: constantinonu
Donate link: https://buymeacoffee.com/constantinonu
Tags: exchange rates, currency converter, bnr, gutenberg block, elementor
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Daily automated BNR exchange rates, currency converter calculator, Gutenberg block, and Elementor widget.

== Description ==

**Onu Exchange Rates and Converter for BNR** brings the official daily exchange rates published by the National Bank of Romania (BNR) directly to your WordPress site. It supports automatic scheduled sync (WP Cron), historical rate lookups, interactive currency conversion forms, classic widgets, a native Gutenberg block, and an Elementor widget.

This plugin performs external HTTP requests to the official BNR XML feed once a day to download current exchange rates. No personal data is sent or collected.

= Features =

* Automatic daily fetch of BNR exchange rates (WordPress Cron)
* Complete historical exchange rate entries saved in a custom database table
* Interactive currency converter calculator widget (RON is the pivot currency)
* `[onuexra_curs]` shortcode for displaying selected rates
* `[onuexra_convertor]` shortcode for rendering the conversion calculator
* Classic sidebar and footer widgets
* Native Gutenberg block with live preview in the editor
* Elementor page builder widget integration (loaded conditionally)
* Daily rate trend variations (increases/decreases marked by color-coded badges)

= Gutenberg Block Source Code =

This plugin contains compiled production Javascript assets inside the `build/` directory for Gutenberg blocks. The original, uncompiled, and fully human-readable source code is bundled inside the plugin package in the `blocks/curs-valutar/src/` and `blocks/convertor-valutar/src/` directories.
To build or modify the block assets yourself, you can run:
1. Install dependencies: `npm install`
2. Build the assets: `npm run build`

= External Services =

This plugin connects to the BNR (National Bank of Romania) XML feed to obtain official daily exchange rates. The data is retrieved once per day via HTTP request to:
- `https://www.bnr.ro/nbrfxrates.xml` (daily rates)
- `https://bnr.ro/files/xml/years/nbrfxrates%d.xml` (historical annual rates)

No personal data, user information, or site data is transmitted to this external service. Only the exchange rate XML files are downloaded.

This service is provided by Banca Națională a României (BNR):
- Website: https://www.bnr.ro/
- Terms of Service: https://www.bnr.ro/Terms-and-conditions-12110.aspx
- Privacy Policy: https://www.bnr.ro/Privacy-policy-12496.aspx

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory, or install directly from Plugins -> Add New.
2. Activate the plugin.
3. Go to Settings -> Onu Exchange Rates to select active currencies and schedule the daily update time.
4. Use the shortcode `[onuexra_curs]`, Gutenberg block, Elementor widget, or Classic widget to display the rates.

== Frequently Asked Questions ==

= Where does the data come from? =
Directly from the official BNR XML feed (bnr.ro), without intermediaries.

= How often is the rate updated? =
Once a day, automatically, at your configured settings hour (defaults to 13:15, Romanian time, which is when BNR publishes the rates).

= Does it work without Elementor? =
Yes. The Elementor widget is loaded only if the Elementor plugin is active. Shortcodes, Gutenberg blocks, and classic widgets work independently.

== Screenshots ==

1. Preview în pagină – shortcode afișat pe o pagină
2. Panoul de setări administrative
3. Tabel cu ratele valutare și sincronizare manuală
4. Gutenberg block preview în editor

== Changelog ==

= 1.0.2 =
* Adăugat branding vizual oficial (logo, iconițe și bannere personalizate pentru WordPress.org).
* Optimizat structura pachetului prin mutarea capturilor de ecran în directorul de active externe (reducând considerabil dimensiunea fișierului ZIP).

= 1.0.1 =
* Resolved manual review feedback: renamed prefixes to prevent conflicts, added uncompiled block source to packaged ZIP, and enhanced option sanitization.

= 1.0.0 =
* Initial release.
