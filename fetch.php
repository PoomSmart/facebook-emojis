<?php

// Based on https://github.com/iamcal/emoji-data/blob/master/build/facebook/grab.php

$emojis = file('emojis.txt', FILE_IGNORE_NEW_LINES);

foreach ($emojis as $code)
{
	fetch($code, 3);
}

function fetch($code, $ratio)
{
	fetch_single($code . '.png', null, 'EMOJI_3', 'images/' . ($ratio * 32), 32, $ratio);
}

function fetch_single($img, $alt_img, $type_key, $dir, $size, $ratio)
{
	$path = "./{$dir}/{$img}";

	if (file_exists($path)) return;

	$types = [
		'FBEMOJI' => 'f',
		'FB_EMOJI_EXTENDED' => 'e',
		'MESSENGER' => 'z',
		'UNICODE' => 'u',
		'COMPOSITE' => 'c',
		'EMOJI_3' => 't',
	];

	$type = $types[$type_key];

	$url = build_url($type, $size, $ratio, str_replace('-', '_', $img));

	if (try_fetch($url, $path)) {
		return;
	}

	if ($alt_img) {
		$url = build_url($type, $size, $ratio, str_replace('-', '_', $alt_img));

		if (try_fetch($url, $path)) {
			return;
		}
	}
}

function build_url($type, $size, $pixelRatio, $img)
{
	$schemaAuth = "https://static.xx.fbcdn.net/images/emoji.php/v9";

	$path = $pixelRatio . '/' . $size . '/' . $img;
	$check = checksum($path);
	$url = $schemaAuth . '/' . $type . $check . '/' . $path;

	return $url;
}

function try_fetch($url, $path)
{
	http_fetch($url, $path);

	if (!file_exists($path)) {
		return false;
	}

	if (!filesize($path)) {
		@unlink($path);
		return false;
	}

	$fp = fopen($path, 'r');
	$sig = fread($fp, 4);
	fclose($fp);

	if ($sig != "\x89PNG") {
		@unlink($path);
		return false;
	}

	return true;
}

function checksum($subpath)
{
	$checksumBase = 317426846;
	$base = $checksumBase;

	for ($pos = 0; $pos < strlen($subpath); $pos++) {
		$base = ($base << 5) - $base + ord(substr($subpath, $pos, 1));
		$base &= 4294967295;
	}
	return base_convert(($base & 255), 10, 16);
}

function http_fetch($url, $filename)
{
	$fh = fopen($filename, 'w');
	if ($fh === false) {
		fwrite(STDERR, "Cannot open {$filename} for writing\n");
		exit(1);
	}

	$ch = curl_init();
	if ($ch === false) {
		fclose($fh);
		fwrite(STDERR, "curl_init failed\n");
		exit(1);
	}

	$ok = curl_setopt_array($ch, [
		CURLOPT_FILE => $fh,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_URL => $url,
		CURLOPT_HTTPHEADER => [
			'Referer: https://www.facebook.com/',
			'User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.2924.87 Safari/537.36',
		],
	]);
	if ($ok === false) {
		fclose($fh);
		fwrite(STDERR, "curl_setopt_array failed: " . curl_error($ch) . "\n");
		exit(1);
	}

	$ok = curl_exec($ch);
	$ret = curl_getinfo($ch);

	fclose($fh);

	echo "({$ret['http_code']}) ($url)\n";
	if ($ok === false || $ret['http_code'] != 200) {
		if ($ok === false) {
			fwrite(STDERR, curl_error($ch) . "\n");
		}
		@unlink($filename);
		exit(1);
	}
}
