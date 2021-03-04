<?php
include_once __DIR__ . '/vendor/autoload.php';
$update = file_get_contents("php://input");

$requisicao = json_decode($update, TRUE);
$funcaoTipster = array(
	"5522997157745-1566406220@g.us" => "funcaoRegys",
	"553195121104-1601482705@g.us" => "funcaoWR",
	"558182315715-1594862914@g.us" => "funcaoFagner",
);


function verificatipster($mensagem, $funcaoTipster){
		if(isset($funcaoTipster[$mensagem["messages"][0]["chatId"]])){
			$funcaoTipster[$mensagem["messages"][0]["chatId"]]($mensagem["messages"][0]["body"]);
		}
}

function funcaoRegys($mensagem){
	$parser = new aymanrb\UnstructuredTextParser\TextParser('vendor/aymanrb/php-unstructured-text-parser/examples/templates');
	//Mudar para diretório referente ao GitHub!!!!
	$textToParse = preg_replace('/^[ \t]*[\r\n]+/m', '', strtolower($mensagem));

	$parseResults = $parser->parseText($textToParse, true)->getParsedRawData();
	if((array_key_exists("time", $parseResults) || array_key_exists("partida", $parseResults)) && strpos($textToParse, "aposta") === false){
		$mercado = defineMercado($textToParse, $parseResults);
		$linhaDB = procuraDB($parseResults, $mercado);
		$parseResults["oddmin"] = calculaOddmin($parseResults["odd"]);
		if(isset($linhaDB)){
			$unidades = calculaUnidade($parseResults, $parseResults["odd"]);	
			$message = construirAposta($linhaDB, $mercado, $parseResults["odd"], $parseResults["oddmin"], $unidades);
			enviaMensagem($message);
		}
	} else {
		$parseResults = $parser->parseText($textToParse)->getParsedRawData();
		if((array_key_exists("time", $parseResults) || array_key_exists("partida", $parseResults))  && strpos($textToParse, "aposta") === false){
			$mercado = defineMercado($textToParse, $parseResults);
			$linhaDB = procuraDB($parseResults, $mercado);
			$parseResults["oddmin"] = calculaOddmin($parseResults["odd"]);
			if(isset($linhaDB)){
			$unidades = calculaUnidade($parseResults, $parseResults["odd"]);	
			$message = construirAposta($linhaDB, $mercado, $parseResults["odd"], $parseResults["oddmin"], $unidades);
			enviaMensagem($message);
			}
		}
	}
	
	registraEstrutura($parseResults, "textoparametizado");
	registraEstrutura($textToParse, "textonaoestruturado");
}

function registraEstrutura($parsedText, $arquivo){
	ob_start();
	var_dump($parsedText);
	$input = ob_get_contents();
	ob_end_clean();
	file_put_contents($arquivo.".log",$input.PHP_EOL,FILE_APPEND);
}
function funcaoWR($mensagem){
	$parser = new aymanrb\UnstructuredTextParser\TextParser('vendor/aymanrb/php-unstructured-text-parser/examples/templatesWR');
	//Mudar para diretório referente ao GitHub!!!!
	$textToParse = preg_replace("/^[ \t]*[\r\n]+/m", "", strtolower($mensagem));
	$textohex = bin2hex($textToParse);
	$textotransf = str_replace("0a", "0d0a", $textohex);
	$textToParse = hex2bin($textotransf);
	$parseResults = $parser->parseText($textToParse, true)->getParsedRawData();
	if(array_key_exists("time", $parseResults) == false && array_key_exists("partida", $parseResults) == false){
		echo "ok";
		$parseResults = $parser->parseText($textToParse)->getParsedRawData();
	}
	registraEstrutura($parseResults, "textoparametizado");
	registraEstrutura($textToParse, "textonaoestruturado");
	if((array_key_exists("time", $parseResults) || array_key_exists("partida", $parseResults)) && strpos($textToParse, "aposta") === false && strpos($textToParse, "live") === false){
		$mercado = defineMercado($textToParse, $parseResults);
		$linhaDB = procuraDB($parseResults, $mercado);
		if(array_key_exists("odd", $parseResults) == false || is_numeric($parseResults["odd"]) == false){
			$parseResults["odd"] = calculaOdd($linhaDB, $mercado);
		}
		$parseResults["oddmin"] = calculaOddmin($parseResults["odd"]);
		if(isset($linhaDB)){
			$unidades = calculaUnidade($parseResults, $parseResults["odd"]);
			$message = construirAposta($linhaDB, $mercado, $parseResults["odd"], $parseResults["oddmin"], $unidades);
			enviaMensagem($message);
		}
	}
}

function funcaoFagner($mensagem){
	$parser = new aymanrb\UnstructuredTextParser\TextParser('vendor/aymanrb/php-unstructured-text-parser/examples/templatesfagner');
	//Mudar para diretório referente ao GitHub!!!!
	$textToParse = preg_replace("/^[ \t]*[\r\n]+/m", "", strtolower($mensagem));
	$parseResults = $parser->parseText($textToParse, true)->getParsedRawData();
	print_r($parseResults);
	registraEstrutura($parseResults, "textoparametizado");
	registraEstrutura($textToParse, "textonaoestruturado");
	if(array_key_exists("time", $parseResults) == false && array_key_exists("partida", $parseResults) == false){
		echo "<br>Nao especifica<br>";
		$parseResults = $parser->parseText($textToParse)->getParsedRawData();
	}
	print_r($parseResults);
	if((array_key_exists("time", $parseResults) || array_key_exists("partida", $parseResults)) && strpos($textToParse, "aposta") === false && strpos($textToParse, "live") === false){
		$mercado = defineMercado($textToParse, $parseResults);
		$linhaDB = procuraDB($parseResults, $mercado);
		if(array_key_exists("odd", $parseResults) == false || is_numeric($parseResults["odd"]) == false){
			$parseResults["odd"] = calculaOdd($linhaDB, $mercado);
		}
		$parseResults["oddmin"] = calculaOddmin($parseResults["odd"]);
		if(isset($linhaDB)){
			$unidades = calculaUnidade($parseResults, $parseResults["odd"]);
			$message = construirAposta($linhaDB, $mercado, $parseResults["odd"], $parseResults["oddmin"], $unidades);
			enviaMensagem($message);
		}
	}
}

function defineMercado($mensagem, $parsedtext){
	if(strpos($mensagem, "ml") !== false){
		$mercado = " - Vitória";
	} else if(strpos($mensagem, "dnb") !== false){
		$mercado = " - Empate Anula Aposta";
	} else if(strpos($mensagem, "dc ht") !== false){
		$mercado = " ou Empate - 1º Tempo";
	} else if(strpos($mensagem, "dc") !== false){
		$mercado = " ou Empate";
	} else if(strpos($mensagem, "htft") !== false || strpos($mensagem, "ht/ft") !== false){
		$mercado = " - Intervalo/Final do Jogo";
	} else if((strpos($mensagem, "over") !== false || strpos($mensagem, "ais de") !== false) && strpos($mensagem, "ht") !== false){
		$mercado = str_replace(array("ht", "gols", "gol"),array("","",""),"Mais de ".$parsedtext["gols"])." gol(s) no 1º Tempo";
	} else if(strpos($mensagem, "over") !== false || strpos($mensagem, "ais de") !== false){
		$mercado = str_replace(array("gols", "gol"),array("",""),"Mais de ".$parsedtext["gols"])." gol(s) na Partida";
	} else if((strpos($mensagem, "under") !== false || strpos($mensagem, "enos de") !== false) && strpos($mensagem, "ht") !== false){
		$mercado = str_replace(array("ht", "gols", "gol"),array("","",""),"Menos de ".$parsedtext["gols"])." gol(s) no 1º Tempo";
	} else if(strpos($mensagem, "under") !== false || strpos($mensagem, "enos de") !== false){
		$mercado = str_replace(array("gols", "gol"),array("",""),"Menos de ".$parsedtext["gols"])." gol(s) na Partida";
	} else if((strpos($mensagem, "ah") !== false || array_key_exists("linhapositiva", $parsedtext) || array_key_exists("linhanegativa", $parsedtext)) && strpos($mensagem, "ht") !== false){
		if(array_key_exists("linhapositiva", $parsedtext)){
			$mercado = " - Handcap Asiático +".$parsedtext["linhapositiva"]." no 1º Tempo";
		}else if(array_key_exists("linhanegativa", $parsedtext)){
			$mercado = " - Handcap Asiático -".$parsedtext["linhanegativa"]." no 1º Tempo";
		} else {
			$mercado = " - Handcap Asiático ".$parsedtext["linha"]." no 1º Tempo";
		}
	} else if(strpos($mensagem, "ah") !== false || array_key_exists("linhapositiva", $parsedtext) || array_key_exists("linhanegativa", $parsedtext)){
		if(array_key_exists("linhapositiva", $parsedtext)){
			$mercado = " - Handcap Asiático +".$parsedtext["linhapositiva"];
		} else if(array_key_exists("linhanegativa", $parsedtext)){
			$mercado = " - Handcap Asiático -".$parsedtext["linhanegativa"];
		} else {
			$mercado = " - Handcap Asiático ".$parsedtext["linha"];
		}
	}
	return $mercado;
}

function procuraDB($aposta, $mercado){
	$db_handle = pg_connect("host=ec2-54-164-241-193.compute-1.amazonaws.com dbname=detfg6vttnaua8 port=5432 user=kgsgrroozfzpnv password=a2ec0dd00478fd02c6395df74d3e82adc94632e51ea2c1cca2ba94f988e591f5");
	$query = "SELECT * FROM tabelateste";
	$resultado = pg_query($db_handle, $query);
	$min = 15;
	$min2 = 23;
	while ($row = pg_fetch_assoc($resultado)){
		if(array_key_exists("time", $aposta)){
			if(levenshtein($aposta["time"], strtolower($row["time1"]), 1, 3, 3)<$min){
				$arrayDB = $row;
				array_push($arrayDB, "time1");
				$min = levenshtein($aposta["time"], strtolower($row["time1"]), 1, 3, 3);
			} if(levenshtein($aposta["time"], strtolower($row["time2"]), 1, 3, 3)<$min){
				$arrayDB = $row;
				array_push($arrayDB, "time2");
				$min = levenshtein($aposta["time"], strtolower($row["time2"]), 1, 3, 3);
			}
		} else if(array_key_exists("partida", $aposta)){
			if(levenshtein($aposta["partida"], strtolower($row["time1"]."  ".$row["time2"]), 1, 3, 3)<$min2){
				$arrayDB = $row;
				array_push($arrayDB, "partida");
				$min2 = levenshtein($aposta["partida"], strtolower($row["time1"]."  ".$row["time2"]), 1, 3, 3);
			}
		}
	}
	if(strpos($mercado, "Mais") !== false || strpos($mercado, "Menos") !== false){
		$mercadoBet = "betgols";
	} else {
		$mercadoBet = "betresultado";
	}
	if(isset($arrayDB) && ($arrayDB["hora"]<time()-600 || $arrayDB[$mercadoBet] == "t")){ //Mudar diferença da hora!!!
		unset($arrayDB);
	}
	if(isset($arrayDB)){
		$arrayDB["partida"] = "";
		$time1 = $arrayDB["time1"];
		$time2 = $arrayDB["time2"];
		if($mercadoBet == "betgols"){
			$busca = "UPDATE tabelateste SET betgols='1' WHERE time1='$time1' and time2='$time2'";
		} else if ($mercadoBet == "betresultado"){
			$busca = "UPDATE tabelateste SET betresultado='1' WHERE time1='$time1' and time2='$time2'";
		}
		$resulta = pg_query($db_handle, $busca);
		return $arrayDB;
	}
}

function calculaOdd($arrayAposta, $mercado){
	if($mercado == " - Vitória"){
		$odd = $arrayAposta["odd".$arrayAposta[0]];
	} else if($mercado == " - Empate Anula Aposta"){
		$odd = ($arrayAposta["odd".$arrayAposta[0]]-($arrayAposta["odd".$arrayAposta[0]]/$arrayAposta["oddempate"]))/0.9375;
	} else {
		$odd = rand(170,190)/100;
	}
	return $odd;
}

function calculaOddmin($apostaestruturada){
	$oddmin = $apostaestruturada*0.8+0.2;
	if($oddmin < 1.56 && $apostaestruturada > 1.56){
		$oddmin = 1.56;
	}
	return $oddmin;
}

function enviaMensagem($message){
	$botToken = "1698766079:AAHhiOkKbYl7IXOQ2TYxDlJqiBw1hRx9rJg";
	$chat_id = "-1001256582495";
	$bot_url    = "https://api.telegram.org/bot".$botToken;
	$url = $bot_url."/sendMessage?chat_id=".$chat_id."&text=".urlencode($message);
	file_get_contents($url);
}

function calculaUnidade($arrayDB, $odd){
	if(rand(1, 10)<=7 && array_key_exists("unidades", $arrayDB)){
		$unidade = $arrayDB["unidades"];
	} else {
		if($odd <= 2.2){
			$unidade = 1;
		} else if($odd <= 3){
			$unidade = 0.5;
		} else {
			$unidade = 0.2;
		}
	}
	return $unidade;
}

function construirAposta($arrayDB, $mercado, $odd, $oddmin, $unidades){
	$mensagem = "⚠️ ".$arrayDB[$arrayDB[0]].$mercado."
💰 ".number_format($unidades, 1)." unidade
⚽️ @".number_format($odd, 2)."
⚽ Mínimo @".number_format($oddmin, 2)."
🏟️ ".$arrayDB["time1"]." x ".$arrayDB["time2"]."
🏟️".$arrayDB["campeonato"]."
".$arrayDB["link"];
	return $mensagem;
}


verificatipster($requisicao, $funcaoTipster);
?>
