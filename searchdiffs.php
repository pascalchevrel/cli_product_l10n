<?

include_once('simple_html_dom.php');
error_reporting(E_ALL & ~E_NOTICE);
if ($argc < 4) die("USO: php searchdiffs.php <run id> <path_ast> <path_es>\n");

$url = 'https://l10n.mozilla.org/dashboard/compare?run='. $argv[1];
$path_ast = trim($argv[2], ' /');
$path_es = trim($argv[3], ' /');

$r = '';
do {
	echo '¿Quieres actualizar el repositorio? (s/n): ';
	$r = strtolower(stream_get_line(STDIN, 10, PHP_EOL));
} while($r != 'n' && $r != 's');

if ($r == 's') {
	chdir(__DIR__ .'/' . $path_ast);
	exec('hg pull -u');
	
	chdir(__DIR__ .'/'. $path_es);
	exec('hg pull -u');
}

chdir(__DIR__);

echo 'Buscando diferencias en: '. $url ."\n";

// Create DOM from URL or file
$html = file_get_html($url);

//Find how much differences
$total = $html->find('#missing');
$total = trim($total[0]->plaintext);
list($total, $c) = explode(' ', $total);

echo 'Hay '. $total .' traducciones pendientes.'. PHP_EOL . PHP_EOL;

// Find all images 
$t = $html->find('.file-path');
$current = 1;

foreach($t as $file_path_container) {

	$file_path = $file_path_container->first_child()->title;
	if ($file_path_container->next_sibling()->class != 'diff') continue;
	
	
	$file_path_arr = explode('/', $file_path);
	
	$es_path_arr = $file_path_arr;
	$es_path_arr[0] = $path_es;
	$es_path = implode('/', $es_path_arr);
	
	$ast_path_arr = $file_path_arr;
	$ast_path_arr[0] = $path_ast;
	$ast_path = implode('/', $ast_path_arr);
	
	unset($file_path_arr, $es_path_arr, $ast_path_arr);
	
	
	$type = substr( $ast_path, strrpos($ast_path, '.')+1);
	
	echo strtoupper($type) .': '. $es_path . PHP_EOL;
	
	$arr = $file_path_container->next_sibling()->find('div');
	$arr = array_reverse($arr);
	foreach($arr as $keyTag) {
		$action = $keyTag->class;
		$key = $keyTag->plaintext;
		
		echo "\033[93m". '  ['. $current++ .'/'. $total .'] '. $key . ' -> '. $action ."\033[0m";
		
		if ($type == 'properties') {
			if ($action == 'missing') {
				$actual_value = PropertiesRead($es_path, $key);
				$value_in_new_file = PropertiesRead($ast_path, $key);
				if (!is_null($value_in_new_file))  { 
					echo ' -> "'. $value_in_new_file .'"'. PHP_EOL;
					continue;
				}
				if (is_null($actual_value)) {
					echo ' -> NO ENCONTRADO (omitimos)'. PHP_EOL;
					continue;
				}
				
				PropertiesAdd($ast_path, $key, GetLineShowingText($actual_value));
			}
			if ($action == 'obsolete')
				PropertiesDel($ast_path, $key);
		}
		elseif($type == 'dtd') {
			if ($action == 'missing') {
				$actual_value = DTDRead($es_path, $key);
				$value_in_new_file = DTDRead($ast_path, $key);
				if (!is_null($value_in_new_file)) {
					echo ' -> "'. $value_in_new_file .'"'. PHP_EOL;
					continue;
				}
				
				if (is_null($actual_value)) {
					echo ' -> NO ENCONTRADO (omitimos)'. PHP_EOL;
					continue;
				}
				
				DTDAdd($ast_path, $key, GetLineShowingText($actual_value));
			}
			if ($action == 'obsolete')
				DTDDel($ast_path, $key);
		}
		
		echo PHP_EOL;
	}
	echo PHP_EOL;
}

function GetLineShowingText($str) {
	echo "\n". '    Cadena encontrada: "'. $str .'"'. PHP_EOL;
	echo '    Cadena traducida: ';
	return stream_get_line(STDIN, 1024, PHP_EOL);
}

function PropertiesAdd($path, $key, $value) {
	$str = trim($key) . ' = '. trim($value) .PHP_EOL;
	file_put_contents($path, $str, FILE_APPEND);
}

function PropertiesRead($path, $key) {
	$arr = file($path);
	foreach($arr as $v) {
		list($tkey, $tvalue) = array_map('trim', explode('=', $v));
		
		if ($tkey == $key) return $tvalue;
	}
}

function PropertiesDel($path, $key) {
	$data = file_get_contents($path);
	
	$arr = file($path);
	
	foreach($arr as $k => $v) {
		list($tkey, $tvalue) = array_map('trim', explode('=', $v));
		
		if ($tkey != $key) continue;
		
		$data = str_replace( $v, '', $data);
		break;
	}
	
	file_put_contents($path, $data);
	
}

function DTDAdd($path, $key, $value) {
	//<!ENTITY webConsoleButton.label "Consola web">
	$str = '<!ENTITY '. trim($key) . ' "'. trim($value) .'">' .PHP_EOL;
	file_put_contents($path, $str, FILE_APPEND);
}
function DTDRead($path, $key) {
	$arr = file($path);
	foreach($arr as $v) {
		list($t, $tkey, $tvalue) = array_map('trim', explode(' ', $v));
		
		$tvalue = trim(substr($v, strpos($v, '"')+1, -2), '"');
		
		if ($tkey == $key) return $tvalue;
	}
}

function DTDDel($path, $key) {
	$data = file_get_contents($path);
	
	$arr = file($path);
	
	foreach($arr as $k => $v) {
		list($t, $tkey, $tvalue) = array_map('trim', explode(' ', $v));
		
		if ($tkey != $key) continue;
		
		$data = str_replace( $v, '', $data);
		break;
	}
	
	file_put_contents($path, $data);
}


?>
