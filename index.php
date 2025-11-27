<?php
// Simple domain lookup / availability checker
// - Does a quick DNS check (A / NS records)
// - Performs a basic WHOIS query (useful for many TLDs)
// Note: This is a small, self-contained helper. For production-grade
// checks or registrar integrations (e.g., SiteGround API) you should
// use the registrar's official API with authentication.

function whois_query(string $domain): string {
	// Determine TLD's WHOIS server using IANA then query it
	// Fallback to common whois servers if necessary.
	$parts = explode('.', $domain);
	if (count($parts) < 2) return "";
	$tld = array_pop($parts);

	// First consult whois.iana.org for the correct server
	$server = "whois.iana.org";

	$resp = whois_lookup_raw($server, $tld);
	if (preg_match('/whois:\s*(\S+)/i', $resp, $m)) {
		$server = trim($m[1]);
	} else {
		// Some common fallbacks for common TLDs
		$fallbacks = [
			'com' => 'whois.verisign-grs.com',
			'net' => 'whois.verisign-grs.com',
			'org' => 'whois.pir.org',
		];
		if (isset($fallbacks[$tld])) $server = $fallbacks[$tld];
	}

	return whois_lookup_raw($server, $domain);
}

function whois_lookup_raw(string $server, string $query): string {
	$port = 43;
	$timeout = 5; // seconds
	$out = '';

	$fp = @fsockopen($server, $port, $errno, $errstr, $timeout);
	if (!$fp) return "";

	fwrite($fp, $query . "\r\n");
	stream_set_timeout($fp, $timeout);
	while (!feof($fp)) {
		$out .= fgets($fp, 128);
	}
	fclose($fp);
	return $out;
}

function is_domain_available(string $domain): array {
	$result = [
		'dns_resolves' => false,
		'whois' => '',
		'available' => null,
	];

	// Quick DNS check (A or NS)
	$hasA = checkdnsrr($domain, 'A');
	$hasNS = checkdnsrr($domain, 'NS');
	$result['dns_resolves'] = $hasA || $hasNS;

	// WHOIS lookup
	$whois = whois_query($domain);
	$result['whois'] = $whois;

	// Try to detect availability from whois response
	// Many registries use "No match" or "NOT FOUND" or "No entries found"
	$notFoundPatterns = [
		'/no match/i',
		'/not found/i',
		'/no entries found/i',
		'/status:\s*available/i',
		'/domain not found/i',
	];

	foreach ($notFoundPatterns as $pat) {
		if (preg_match($pat, $whois)) {
			$result['available'] = true;
			break;
		}
	}

	// If WHOIS didn't return a clear hit and DNS resolves, assume taken
	if ($result['available'] === null) {
		$result['available'] = !$result['dns_resolves'];
	}

	return $result;
}

$noCacheHeaders = [
	'Cache-Control: no-store, no-cache, must-revalidate, max-age=0',
	'Pragma: no-cache',
	'Expires: Mon, 26 Jul 1997 05:00:00 GMT',
	'Surrogate-Control: no-store',
];
// Send no-cache headers early (safe here because no output has been sent yet)
foreach ($noCacheHeaders as $h) {
	@header($h);
}

// Simple form handler
$error = null;
$out = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$domain = trim($_POST['domain'] ?? '');
	// basic domain sanity check
	if ($domain === '') {
		$error = 'Enter a domain name (example: example.com)';
	} elseif (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $domain)) {
		$error = 'This does not look like a valid domain name.';
	} else {
		$out = is_domain_available($domain);
	}
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Domain lookup (SiteGround-friendly)</title>
	<style>
		:root{
			--bg:#ffffff;
			--text:#222222;
			--muted:#666666;
			--panel:#fafafa;
			--accent:#0b5fff;
			--border:#ddd
		}
		.dark{
			--bg:#0b0f13;
			--text:#e6eef6;
			--muted:#a7b3c3;
			--panel:#0f1720;
			--accent:#6ea8ff;
			--border:#23313b;
		}

		body{font-family:system-ui,Segoe UI,Arial;color:var(--text);background:var(--bg);padding:28px}
	form{max-width:720px;margin-bottom:18px}
	input[type=text]{width:60%;padding:8px;font-size:16px}
	input[type=submit]{padding:8px 12px;font-size:16px}
	pre{background:rgba(0,0,0,0.8);color:#eee;padding:12px;border-radius:6px;overflow:auto}
	.note{color:var(--muted);margin-top:12px;font-size:14px}
	a.button{background:var(--accent);color:white;padding:8px 10px;border-radius:5px;text-decoration:none}
  </style>
</head>
<body>
	<div style="max-width:1100px;display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px">
		<h1 style="margin:0">Quick domain lookup</h1>
		<!-- Dark mode toggle -->
		<div style="display:flex;align-items:center;gap:8px;font-size:14px">
			<label for="darkToggle" style="display:flex;align-items:center;gap:8px;cursor:pointer;color:var(--muted)">
				<input id="darkToggle" type="checkbox" style="width:28px;height:18px;vertical-align:middle" />
				<span id="darkToggleLabel">Dark mode</span>
			</label>
		</div>
	</div>
	<p class="note">This helper performs a basic DNS + WHOIS lookup. For full registrar integration (including registration through SiteGround) use their official API and credentials.</p>

	<div style="display:flex;gap:18px;align-items:flex-start;max-width:1100px">
		<div style="flex:1">
			<form method="post">
	<label for="domain">Domain</label>
	<input id="domain" name="domain" autofocus placeholder="example.com" type="text" value="<?php echo isset($domain) ? htmlspecialchars($domain) : ''; ?>" />
		<input type="submit" value="Check" />

		<div style="margin-top:10px">
			<label style="display:block;margin-bottom:8px"><input type="checkbox" name="scan_subdomains" value="1" <?php echo !empty($_POST['scan_subdomains']) ? 'checked' : ''; ?> /> Scan common subdomains</label>

			<label style="display:block;margin-bottom:8px">Wordlist (optional - newline separated). Leave blank to use built-in list.
				<br /><textarea name="custom_wordlist" rows="4" style="width:90%;margin-top:6px"><?php echo isset($_POST['custom_wordlist']) ? htmlspecialchars($_POST['custom_wordlist']) : ''; ?></textarea>
			</label>

			<label style="display:block;margin-top:8px;font-size:13px;color:#666"><input type="checkbox" name="show_only_available" value="1" <?php echo !empty($_POST['show_only_available']) ? 'checked' : ''; ?> /> Show only available (no DNS records / whois says not found)</label>
		</div>
		</form>
		</div>

		<!-- Right column: AdSense / sidebar placeholder -->
		<aside style="width:300px;flex-shrink:0;border:1px dashed #ddd;padding:16px;background:#fafafa;border-radius:6px">
			<div style="font-weight:600;margin-bottom:8px">Ad placeholder</div>
			<div style="background:#eee;height:250px;display:flex;align-items:center;justify-content:center;color:#666;border-radius:4px">Google AdSense / Promo (300×250)</div>
			<p style="font-size:12px;color:#666;margin-top:10px">Replace this block with your AdSense code when you're ready: &lt;script&gt;...Adsense code...&lt;/script&gt;</p>
		</aside>
	</div>
  </form>

  <?php if ($error): ?>
	<div style="color:#b00;padding:8px;border:1px solid #f2c2c2;background:#fff6f6;max-width:680px"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

	<?php if ($out): ?>
	<h2>Results for <?php echo htmlspecialchars($domain); ?></h2>
	<ul>
	  <li>DNS resolves (A or NS): <strong><?php echo $out['dns_resolves'] ? 'yes' : 'no'; ?></strong></li>
	  <li>WHOIS implies available: <strong><?php echo $out['available'] ? 'yes' : 'no'; ?></strong></li>
	</ul>

	<h3>Raw WHOIS</h3>
	<pre><?php echo htmlspecialchars($out['whois']); ?></pre>

	<h3>Notes on SiteGround</h3>
	<p class="note">If you want to check or register domains specifically via SiteGround's registrar, you must use their registrar API / SOAP or REST endpoints and an API key. Provide API details and I can add an option that calls SiteGround's endpoint for availability and registration. This script does not call SiteGround APIs by default.</p>
  <?php endif; ?>

	<!-- Subdomain scan output -->
	<?php
		// If scanning is requested, run subdomain checks.
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['scan_subdomains']) && empty($error) ) {
			// load built-in wordlist unless user provided custom list
			$rawList = trim($_POST['custom_wordlist'] ?? '');
			if ($rawList === '') {
					$wordlistPath = __DIR__ . '/data/subdomains.txt';
					if (is_readable($wordlistPath)) {
							$rawList = file_get_contents($wordlistPath);
					}
			}

			$items = preg_split('/[\r\n,]+/', $rawList, -1, PREG_SPLIT_NO_EMPTY);
			// Safety limit: avoid scanning huge lists
			$limit = 200;
			if (count($items) > $limit) $items = array_slice($items, 0, $limit);

			$domainRoot = $domain ?? '';
			if ($domainRoot) {
				echo '<h2>Subdomain scan results</h2>';
				echo '<p class="note">Scanning up to ' . count($items) . ' subdomains. (Basic DNS checks using checkdnsrr). Results may be approximate.</p>';

				$found = [];
				$onlyAvailable = !empty($_POST['show_only_available']);
				foreach ($items as $sub) {
					$sub = trim($sub);
					if ($sub === '') continue;
					$fqdn = strtolower($sub . '.' . $domainRoot);

					// Basic DNS checks
					$hasA = checkdnsrr($fqdn, 'A');
					$hasCNAME = checkdnsrr($fqdn, 'CNAME');
					$hasNS = checkdnsrr($fqdn, 'NS');

					$availableGuess = !($hasA || $hasCNAME || $hasNS);
					if ($onlyAvailable && !$availableGuess) continue;

					$found[] = [
						'sub' => $sub,
						'fqdn' => $fqdn,
						'a' => $hasA,
						'cname' => $hasCNAME,
						'ns' => $hasNS,
						'available' => $availableGuess,
					];
				}

				if (count($found) === 0) {
					echo '<div style="padding:12px;border:1px solid #eee;background:#fff7f7;max-width:760px">No results found (or nothing matched your filters).</div>';
				} else {
					echo '<table style="border-collapse:collapse;margin-top:12px;width:100%;max-width:920px">';
					echo '<thead><tr style="text-align:left;border-bottom:1px solid #ddd"><th style="padding:8px">sub</th><th style="padding:8px">fqdn</th><th style="padding:8px">A</th><th style="padding:8px">CNAME</th><th style="padding:8px">NS</th><th style="padding:8px">available</th></tr></thead>';
					echo '<tbody>';
					foreach ($found as $r) {
						echo '<tr style="border-bottom:1px solid #f2f2f2"><td style="padding:8px">' . htmlspecialchars($r['sub']) . '</td><td style="padding:8px">' . htmlspecialchars($r['fqdn']) . '</td><td style="padding:8px">' . ($r['a'] ? 'yes' : '-') . '</td><td style="padding:8px">' . ($r['cname'] ? 'yes' : '-') . '</td><td style="padding:8px">' . ($r['ns'] ? 'yes' : '-') . '</td><td style="padding:8px">' . ($r['available'] ? '<strong style="color:green">likely</strong>' : '<strong style="color:#b00">no</strong>') . '</td></tr>';
					}
					echo '</tbody></table>';
				}
			}
		}
	?>

</body>
<script>
// Dark mode toggle + persist
(function(){
	try {
		const el = document.getElementById('darkToggle');
		const docEl = document.documentElement;
		if (!el) return;
		const stored = localStorage.getItem('darkmode');
		const prefers = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
		const useDark = stored === null ? prefers : (stored === '1');
		if (useDark) docEl.classList.add('dark');
		el.checked = useDark;
		el.addEventListener('change', function(e){
			const on = !!e.target.checked;
			if (on) docEl.classList.add('dark'); else docEl.classList.remove('dark');
			try{ localStorage.setItem('darkmode', on ? '1' : '0'); }catch(e){}
		}, {passive:true});
	} catch(e) { console.warn('dark toggle failed', e); }
})();
</script>
<footer style="padding:20px 28px;color:#666;font-size:13px;max-width:1100px;margin-top:46px">
	<!-- Google Analytics placeholder: replace with your GA/gtag code -->
	<!-- Example: <script async src="https://www.googletagmanager.com/gtag/js?id=GA_MEASUREMENT_ID"></script>
			 <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','GA_MEASUREMENT_ID');</script>
	-->
	<div style="opacity:.9">Google Analytics placeholder — add your tracking snippet here.</div>
</footer>
</html>



