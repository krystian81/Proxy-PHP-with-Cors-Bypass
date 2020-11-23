<?php
	session_name('WSID');
	session_start();
	
	$debugowanie = false;
	start($debugowanie);
	
	function czyURLAdresuWtyczki($url) {
		$plik = __DIR__ . "/Moduly/manifest.txt";
		
		if(file_exists($plik)) {
			foreach(file($plik) as $linia) {
				if(strcasecmp(trim($linia), trim($url)) == 0) {
					return true;
				}
			}
		}
		return false;	
	}

	function czyPodmieniacURL($domena) {
		if($domena == "portfel.lotto.pl") {
			return false;
		}
		return true;
	}
	
	function dopiszSkrypt(&$url, &$html) {
		if(czyURLAdresuWtyczki($url)) {
			$katalog = strpos(strtolower($url), "szybkie-600") !== FALSE?"Szybkie600":"Keno";			

			if(strpos(strtolower($url), "wager-confirmation")) {
				$katalog == isset($_SESSION["automat_katalog"])?$_SESSION["automat_katalog"]:"Keno";
			} else {
				$_SESSION["automat_katalog"] = $katalog;
			}
						
			$skrypty = "
<script type='text/javascript' src='/Moduly/" . $katalog . "/sortowanie.js'></script>\r\n
<script type='text/javascript' src='/Moduly/" . $katalog . "/tablica.js'></script>\r\n
<script type='text/javascript' src='/Moduly/" . $katalog . "/gra.js'></script>\r\n
<script type='text/javascript' src='/Moduly/" . $katalog . "/wynikTypowania.js'></script>\r\n
<script type='text/javascript' src='/Moduly/" . $katalog . "/system.js'></script>\r\n
<script type='text/javascript' src='/Moduly/" . $katalog . "/aktualizatorWynikow.js'></script>\r\n
<script type='text/javascript' src='/Moduly/" . $katalog . "/baza.js'></script>\r\n
<script type='text/javascript' src='/Moduly/" . $katalog . "/wykonajTypowania.js'></script>\r\n
<script type='text/javascript' src='/Moduly/" . $katalog . "/automatycznaGra.js'></script>\r\n
<script type='text/javascript' src='/Moduly/" . $katalog . "/content.js'></script>\r\n
			";
		
			return preg_replace("/<\/head>/i", "$skrypty</head>", $html, 1);
		}
		return $html;
	}
	
	function przekieruj($url) {
		header('Location: ' . $url);
		die;
	}
	
	function wyswietlStronePrzekierowania($url, $domena) {
		$skrypt = "
			function removeAllCookies() {
				var cookies = document.cookie.split(';');
				for (var i = 0; i < cookies.length; i++) {
					var cookie = cookies[i];
					var eqPos = cookie.indexOf('=');
					var name = eqPos > -1 ? cookie.substr(0, eqPos) : cookie;
					
					if(name != 'WSID') {
						document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT';
					}
				}
			}

			window.onload = function() {
				navigator.serviceWorker.getRegistrations().then(
					function(registrations) {
						for(let registration of registrations) { 
							registration.unregister();
						} 
					}
				);" . (pobierzNajnizszaDomene(strtolower($domena)) != "lotto.pl"?" 
				localStorage.clear();
				sessionStorage.clear();
				removeAllCookies();
				":"") . "
				document.location = '" . $url . "';			
			};
		";
		
		echo "<html><head></head><body><script>" . $skrypt . "</script></body></html>";
	}
	
	function gunzip($zipped) {
        $offset = 0;
        if (substr($zipped,0,2) == "\x1f\x8b") {
            $offset = 2;
        }
        if (substr($zipped,$offset,1) == "\x08")  {
            return gzinflate(substr($zipped, $offset + 8));
        }
        return null;
    }  
	
	function usunCookie(&$cookies, $wartosc) {
		$arr = preg_split("/\\s*[;]\\s*/", $cookies);
		$noweCookie = "";
		for($i = 0; $i < count($arr); $i++) {
			$cookie = preg_split("/\\s*=\\s*/", $arr[$i]);
			if(count($cookie) == 2) {
				if(preg_match("/" . $wartosc . "/i", $cookie[0])) {
					continue;
				}
				$noweCookie .= $cookie[0] . "=" . $cookie[1] . ";";
			}
			if(count($cookie) == 1) {
				if(preg_match("/" . $wartosc . "/i", $cookie[0])) {
					continue;
				} else {
					$noweCookie .= $cookie[0] . ";";
				}
			}
		}
		return $noweCookie;
	}
	
	function aktualizujDane(&$uchwyt, &$response) {
        if($response == null) {
            return null;
        }    
        $kod = curl_getinfo($uchwyt, CURLINFO_RESPONSE_CODE);
        $header_size = curl_getinfo($uchwyt, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $header_size) . "\n";        

        $kodowany = false;

		$headerarr = preg_split('/\r\n/', $header, -1, PREG_SPLIT_NO_EMPTY);
		
        if(preg_match('/^Content-Encoding: gzip/mi', $header)) {
            $kodowany = true;
        }
         
		$zawartosc = "";
        if($kodowany) {
            $zawartosc = gunzip(substr($response, $header_size, strlen($response)));
        } else {
			$zawartosc = substr($response, $header_size, strlen($response));
		}
		$rezultat = ["kod" => $kod, "naglowki" => $headerarr, "zawartosc" => $zawartosc];
		return $rezultat;
    }
	
	function wypiszNaglowek($serwer, $domena, $poddomena, &$naglowki) {
		$powrot = "";
		for($i = 0; $i < count($naglowki); $i++) {
			$wierszArr = preg_split("/\\s*[:]\\s*/", $naglowki[$i], 2);
			if(count($wierszArr) == 2) {
				if(preg_match("/Location/i", $wierszArr[0])) {
					$wierszArr[1] = formatujZawartosc($serwer, $domena, $poddomena, "text/html", $wierszArr[1]);
				}
				if(preg_match("/Content-type/i", $wierszArr[0])) {
					$powrot = trim($wierszArr[1]);
				}
				if(preg_match("/X-Frame-Options/i", $wierszArr[0])) {
					continue;
				}
				if(preg_match("/X-XSS-Protection/i", $wierszArr[0])) {
					continue;
				}
				if(preg_match("/Content-Length/i", $wierszArr[0])) {
					continue;
				}
				if(preg_match("/Transfer-Encoding/i", $wierszArr[0])) {
					continue;
				}
				if(preg_match("/Content-Encoding/i", $wierszArr[0])) {
					continue;
				}
				if(preg_match("/Host/i", $wierszArr[0])) {				
					continue;
				}
				if(preg_match("/Set-Cookie/i", $wierszArr[0])) {				
					$wierszArr[1] = usunCookie($wierszArr[1], "Domain");
					$wierszArr[1] = usunCookie($wierszArr[1], "Secure");
				}
				if(preg_match("/Access-Control-Allow-Origin/i", $wierszArr[0])) {
					continue;
				}
				if(preg_match("/Access-Control-Max-Age/i", $wierszArr[0])) {
					continue;
				}
				header($wierszArr[0] . ": " . $wierszArr[1]); 
			} else {
				header($naglowki[$i]);
			}
		}
		return $powrot;
	}
	
	function formatujNaglowek($protokol, $serwer, $domena, $poddomena, &$naglowki) {
		$formatNaglowka = [];
		$referer = null;
		$origin = null;
		$modifiedSince = null;
		$xRequestedWith = null;
		$fetchDest = null;
		$fetchMode = null;
		$encoding = null;
		
		foreach ($naglowki as $nazwa => $wartosc) {
			$wartosc = str_replace($serwer, $domena, $wartosc);
			
			if(preg_match("/Referer/i", $nazwa)) {
				$referer = $wartosc;
				continue;
			}
			if(preg_match("/Origin/i", $nazwa)) {
				$origin = $wartosc;
				continue;
			}
			if(preg_match("/Sec-Fetch-Dest/i", $nazwa)) {
				$fetchDest = $wartosc;
			}
			if(preg_match("/Sec-Fetch-Mode/i", $nazwa)) {
				$fetchMode = $wartosc;
			}
			/*
			if(preg_match("/Sec-Fetch-Site/i", $nazwa)) {
				$wartosc = "same-origin";
			}
			if(preg_match("/Sec-Fetch-User/i", $nazwa)) {
				$wartosc = "?1";
			}
			*/
			if(preg_match("/Accept-encoding/i", $nazwa)) {
				$encoding = "gzip";
				continue;
			}
			if(preg_match("/WSID/i", $nazwa)) {
				continue;
			}
			if(preg_match("/Content-Length/i", $nazwa)) {
				continue;
			}
			if(preg_match("/Connection/i", $nazwa)) {
				$wartosc = "close";
			}
			if(preg_match("/Cookie/i", $nazwa)) {
				$wartosc = usunCookie($wartosc, "WSID");
			}		
			if(preg_match("/If-Modified-Since/i", $nazwa)) {
				$modifiedSince = $wartosc;
				continue;
			}				
			if(preg_match("/If-Modified-Since/i", $nazwa)) {
				$modifiedSince = $wartosc;
				continue;
			}				
			if(preg_match("/X-Requested-With/i", $nazwa)) {
				$xRequestedWith = $wartosc;
			}							
			if(preg_match("/Host/i", $nazwa)) {
				continue;
			}
			$formatNaglowka[] = $nazwa . ": " . $wartosc;
		}
		return ["referer" => $referer, "origin" => $origin, "encoding" => $encoding, "fetchMode" => $fetchMode, "fetchDest" => $fetchDest, "modifiedSince" => $modifiedSince, "xRequestedWith" => $xRequestedWith, "formatNaglowka" => $formatNaglowka];
	}

	function formatujZawartosc($serwer, $domena, $poddomena, $contentType, &$zawartosc) {
		$war = false;
		$war |= strpos(strtolower($contentType), "application/javascript") !== FALSE; 
		$war |= strpos(strtolower($contentType), "text/html") !== FALSE; 
		$war |= strpos(strtolower($contentType), "application/json") !== FALSE; 
		$war |= strpos(strtolower($contentType), "text/css") !== FALSE; 
		
		if($war) {
			$zawartosc = preg_replace("/(?<=\/\/)(" . $domena . ")/", $serwer, $zawartosc);
			$zawartosc = preg_replace("/(?<=\/\/)(\\w+?)([.])(" . $domena . ")/", $serwer . "/?" . "WSVS=" . "$1?/", $zawartosc);
			if($domena != $poddomena) {
				$zawartosc = preg_replace("/(?<=\/\/)(\\w+?)([.])(" . $poddomena . ")/", $serwer . "/?" . "WSVP=" . "$1?/", $zawartosc);
			}
		}			
		return $zawartosc;
	}	
		
	function pobierzParametr($nazwa, &$queryString) {
		if($nazwa == "download_url" && strlen($queryString) > 1) {
			if($queryString[0] == "?") {
				$url = substr($queryString, 1);
				return $url;
			} else {
				return null;
			}
		}
		$parametry = explode("&", $queryString);
		for($i = 0; $i < count($parametry); $i++) {
			$parametrArr = explode("=", $parametry[$i]);
			if($parametrArr[0] == $nazwa && count($parametrArr) == 2) {
				return $parametrArr[1];
			}
		}
		return null;
	}
	
	function pobierzNajnizszaDomene($domena) {
		$poddomenyArr = explode(".", $domena);
		if($poddomenyArr >= 3) {
			return $poddomenyArr[count($poddomenyArr) - 2] . "." . $poddomenyArr[count($poddomenyArr) - 1]; 
		} else {
			return $domena;
		}
	}
	
	function pobierzDomene($protokol, $serwer, $downloadURL, &$queryString) {
		if($downloadURL != null) {			
			$domena = "";
			$sciezka = "";

			if(preg_match("/(?<=\/\/)([^\/]*)(\/)?(.*)?/i", $downloadURL, $matches)) {			
				if(count($matches) >= 2) {
					$domena = $matches[1];
				}			
				if(count($matches) == 4 && $matches[3] !== "") {
					$sciezka = $matches[2] . $matches[3];
				}
				$_SESSION["download_domain"] = $domena;
			}
			
			if($domena == "") {
				if(preg_match("/([^\/]*)(\/)?(.*)?/i", $downloadURL, $matches)) {			
					if(count($matches) >= 2) {
						$domena = $matches[1];
					}			
					if(count($matches) == 4 && $matches[3] !== "") {
						$sciezka = $matches[2] . $matches[3];
					}
					$_SESSION["download_domain"] = $domena;
				}
			}
			
			if($domena != "") {
				$url = $protokol . "://" . $serwer . $sciezka;		
				wyswietlStronePrzekierowania($url, $domena);
				die;
			}
		}
		if(isset($_SESSION["download_domain"])) {
			return $_SESSION["download_domain"];
		}
		die;
	}

	function formatujURI($serwer, $domena, &$uri, $czyReferer) {
		$nowaDomena = $domena;		

		if($czyReferer || czyPodmieniacURL($domena)) {
			$uri = preg_replace("/(?<=\/\/)(" . $serwer . ")/i", $domena, $uri);
			$uri = preg_replace("/(?<=%2F%2F)(" . $serwer . ")/i", $domena, $uri);
		}

		if(preg_match("/[?]WSVS[=]([^?]*)[?]\//i", $uri, $matches)) {
			if(count($matches) >= 2) {
				if(substr($domena, 0, strlen($matches[1])) !== $matches[1]) {
					$nowaDomena = $matches[1] . "." . $domena;
				}
			}
			$uri = preg_replace("/[?]WSVS.*?\?\/{1,2}/", "", $uri);
		}

		if(preg_match("/[?]WSVP[=]([^?]*)[?]\//i", $uri, $matches)) {
			if(count($matches) >= 2) {
				if(!substr($domena, 0, strlen($matches[1])) !== $matches[1]) {
					$nowaDomena = $matches[1] . "." . pobierzNajnizszaDomene($domena);
				}
			}
			$uri = preg_replace("/[?]WSVP.*?\?\/\/{1,2}/", "", $uri);
		}	
		return ["uri" => $uri, "domena" => $nowaDomena];
	}
	
	function ustawNowegoHosta($uchwyt) {
		$daneWyjsciowe = curl_getinfo($uchwyt, CURLINFO_HEADER_OUT);
		
		if(preg_match("/Host:\\s*([^\\s]*)/", $daneWyjsciowe, $matches)) {
			$nowyHost = $matches[1];
			if($nowyHost != null && $nowyHost != "") {
				$_SESSION["download_domain"] = $nowyHost;		
				$domena = $_SESSION["download_domain"];		
			}
		}
	}
	
	function obsluzDebugowanie(&$uchwyt, $debugowanie) {
		if($debugowanie) {
			curl_setopt($uchwyt, CURLOPT_VERBOSE, true);
			$uchwytDebug = fopen("./debug/debug" . time() . ".txt", 'w+');
			curl_setopt($uchwyt, CURLOPT_STDERR, $uchwytDebug);
			return $uchwytDebug;
		}   
		return null;
	}
	
	function start($debugowanie = false) {
		$serwer = $_SERVER['HTTP_HOST'];	
		
		$protokol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
		$domena = null;

		$uri = isset($_SERVER['REQUEST_URI'])?$_SERVER['REQUEST_URI']:"";	

		$queryString = "";	
		if(strpos($uri, "?") >= 0) {
			$queryString = substr($uri, strpos($uri, "?") + 1);
		}

		$downloadURL = pobierzParametr("download_url", $queryString);
		$domena = pobierzDomene($protokol, $serwer, $downloadURL, $queryString);
		
		$orgDomena = $domena;
		$orgUri = $uri;
		
		$rezultat = formatujURI($serwer, $domena, $uri, false);
		
		$uri = $rezultat["uri"];

		$czyDomenaAdresowa = false;
		if($rezultat["domena"] != $domena) {
			$domena = $rezultat["domena"];
			$czyDomenaAdresowa = true;
		}
		
		if($domena == null || trim($domena) == "") {
			die;
		}
		
		$poddomena = pobierzNajnizszaDomene($domena);

		$metoda = $_SERVER['REQUEST_METHOD'];
		$zawartosc = file_get_contents('php://input');
		
		$naglowki = getallheaders();
		
		$rezultatArr = formatujNaglowek($protokol, $serwer, $domena, $poddomena, $naglowki);
		$formatNaglowka = $rezultatArr["formatNaglowka"];
		$referer = $rezultatArr["referer"];
		$origin = $rezultatArr["origin"];
		$modifiedSince = $rezultatArr["modifiedSince"];
		$xRequestedWith = $rezultatArr["xRequestedWith"];
		$fetchMode = $rezultatArr["fetchMode"];
		$fetchDest = $rezultatArr["fetchDest"];
		$encoding = $rezultatArr["encoding"];
		
		if($referer != null) {
			$refererArr = formatujURI($serwer, $orgDomena, $referer, true);
			$referer = $refererArr["uri"];
			$formatNaglowka[] = "Referer: " . $referer;		

			if(!$czyDomenaAdresowa) {
				$domena = $refererArr["domena"];
			}
		}
				
		if($origin != null) {
			$formatNaglowka[] = "Origin: " . $protokol . "://" . $domena;		
		}
		
		if($fetchDest != null) {
			if(strtolower($fetchDest) != "image") {
				$formatNaglowka[] = "Accept-Encoding: " . $encoding;
			}
		}
		
		if($fetchMode != null && $fetchDest != null) {
			if(strtolower($fetchMode) == "navigate" && strtolower($fetchDest) == "document") {
				if($orgDomena != $domena) {
					$_SESSION["download_domain"] = $domena;
					$url = $protokol . "://" . $serwer . ($uri != ""?$uri:"");
					przekieruj($url);
				}
			}
		}

		$url = $protokol . "://" . $domena . ($uri != ""?$uri:"");
		
		if($modifiedSince != null && !czyURLAdresuWtyczki($url)) {
			$formatNaglowka[] = "If-Modified-Since: " . $modifiedSince;				
		}
		
		$uchwyt = curl_init();	
		$uchwytDebug = null;

		if($debugowanie) {
			$uchwytDebug = obsluzDebugowanie($uchwyt, $debugowanie);
		}

		curl_setopt($uchwyt, CURLOPT_URL, $url);
		curl_setopt($uchwyt, CURLOPT_CUSTOMREQUEST, $metoda);
		curl_setopt($uchwyt, CURLOPT_HEADER, true);
		curl_setopt($uchwyt, CURLOPT_HTTPHEADER, $formatNaglowka);
		curl_setopt($uchwyt, CURLOPT_COOKIESESSION, true);
		curl_setopt($uchwyt, CURLOPT_COOKIEFILE, "cookie.txt");
		curl_setopt($uchwyt, CURLOPT_COOKIEJAR, "cookie.txt");
		curl_setopt($uchwyt, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($uchwyt, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($uchwyt, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($uchwyt, CURLOPT_TIMEOUT, 30);

		curl_setopt($uchwyt, CURLOPT_FOLLOWLOCATION, false);		
		curl_setopt($uchwyt, CURLOPT_MAXREDIRS, 0);

		curl_setopt($uchwyt, CURLOPT_AUTOREFERER, false);
		
		if($referer != null) {
			curl_setopt($uchwyt, CURLOPT_REFERER, $referer);
		}

		if($metoda == "POST") {
			curl_setopt($uchwyt, CURLOPT_POST, 1);
			curl_setopt($uchwyt, CURLOPT_POSTFIELDS, $zawartosc);
		}
				
		$odpowiedz = curl_exec($uchwyt);
					
		if($uchwytDebug != null) {
			fclose($uchwytDebug);
		}

		if($odpowiedz !== FALSE) {
			$odpowiedzArr = aktualizujDane($uchwyt, $odpowiedz);
			$contentType = wypiszNaglowek($serwer, $domena, $poddomena, $odpowiedzArr['naglowki']); 
			$odpowiedz = formatujZawartosc($serwer, $domena, $poddomena, $contentType, $odpowiedzArr['zawartosc']);
			$odpowiedz = dopiszSkrypt($url, $odpowiedz);
			echo $odpowiedz;
		} else {
			header("HTTP/1.0 404 Not Found");
			echo "Document not found<br/>";
			
			if($debugowanie) {
				printf("Error %s(#%d): %s<br>\n", $url, curl_errno($uchwyt), htmlspecialchars(curl_error($uchwyt)));
			}
		}

		die;
	}