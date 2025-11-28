# Domain lookup helper (SiteGround-friendly)

This repository contains a tiny PHP domain lookup helper that performs DNS checks and WHOIS queries. The project is intentionally minimal — it is suitable as a small utility or as a starting point for integrating with a registrar API (eg. SiteGround).

What was added
- `index.php` — a small web UI + form that checks DNS (A/NS) and performs a WHOIS lookup to infer domain availability.

AdSense and Google Analytics placeholders
 - The UI includes a right-column Ad placeholder (300x600) in `index.php` (within the <aside> block) — replace this block with your AdSense script when you're ready.
 - The UI includes a right-column Ad placeholder (300x600) in `index.php` (within the <aside> block). The placeholder is non-executing — a sample AdSense snippet is commented out locally inside `index.php`. Replace that placeholder with your own publisher snippet when you are ready to enable ads.
- There is also a Google Analytics / gtag placeholder in the footer (commented example) which you can replace with your GA measurement ID.

New features
- Subdomain scanner: check common subdomains using a built-in wordlist (`data/subdomains.txt`) or paste a custom newline-separated wordlist in the form.
- UI layout includes an AdSense/advert placeholder in the right column (replace with your AdSense code) and a Google Analytics placeholder snippet in the footer.

How to run locally
1. Start the built-in PHP server in the repository root:

```bash
php -S localhost:8000
```

2. Open your browser at `http://localhost:8000/` and try a domain like `example.com`.

Using the subdomain scanner
- Check the "Scan common subdomains" box and optionally supply a custom list. The tool performs basic DNS checks (A/CNAME/NS) and guesses availability if no records are found.
- The scanner has a safety limit to avoid scanning huge lists (first 200 entries are used).

Notes about SiteGround/registrar integration
- This helper does not call SiteGround APIs. SiteGround's registrar endpoints require API keys and authenticated calls; if you want integration, provide API credentials and the API endpoints and I can add a secure wrapper that queries availability and — optionally — creates registrations.
- If you prefer a registrar-backed check, we will need the SiteGround API docs (endpoint URL and auth method) or a service account/key.

Security and reliability
- This script is sample/demo quality — for production use, add proper rate-limiting, input sanitization, caching, error handling, logging, tests, and secure storage of API keys / secrets.

Next steps (pick one)
1. Scaffold composer.json + phpunit tests and add CI
2. Add SiteGround API integration (requires API info)
3. Keep as a lightweight utility

If you want any of the next steps, tell me which and whether you'd like PHP-only or a framework (Slim/Laravel) scaffold.

Dark mode
- `index.php` now includes a top-right dark mode toggle. The toggle persists the user's choice to localStorage and respects the system `prefers-color-scheme` when no saved preference exists.

Styles
- Page styles have been moved into `assets/style.css`. Update that file to change layout or color variables (the stylesheet contains CSS variables and a `.dark` mode configuration).

Branding / logo
- A simple SVG placeholder logo has been added at `assets/logo.svg` and is shown in the top-left of the header. Replace this with your own logo (SVG/PNG) or point the <img> to a hosted asset.
- The repository includes `assets/glitchdata_logo1.png` (the current header logo) and a starter `assets/logo.svg`. Replace either file with your preferred logo (SVG/PNG) or point the <img> to a hosted asset.

Favicon
- `assets/glitchdata_logo1.png` is used as the site's favicon (`<link rel="icon">`) and as the `apple-touch-icon`. Replace the PNG or add other sizes if you prefer a dedicated favicon file (ICO or separate PNG sizes for best cross-device support).

Favicons
- Added an SVG favicon at `assets/favicon.svg` (a small 'SG' badge) and kept `assets/glitchdata_logo1.png` as a PNG fallback and apple-touch-icon.
- You can pre-generate PNG and ICO favicons from the logo using the included script:

```bash
php scripts/generate_favicons.php
```

This will generate:
- `assets/favicon-16x16.png`
- `assets/favicon-32x32.png`
- `assets/favicon-48x48.png`
- `assets/favicon-180x180.png` (apple touch)
- `assets/favicon.ico` (simple ICO containing the 32x32 PNG)

If you prefer, I can pre-generate all files and commit them for you instead of including a generator script.

Library
- Domain helper functions were moved into a simple library at `src/DomainLookup.php`. Use that file when you need the WHOIS/DNS helpers in other scripts — it includes both a `DomainLookup` class and procedural wrappers (`whois_query`, `is_domain_available`, etc.) for backwards compatibility.
