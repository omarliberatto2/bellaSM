<?php

header('Content-Type: text/html; charset=UTF-8');  

define('IN_SCRIPT', 1);
define('HESK_PATH', '../');
define('WYSIWYG', 1);
define('VALIDATOR', 1);
define('MFH_PAGE_LAYOUT', 'TOP_ONLY');
define('PAGE_TITLE', 'informePresidenteMapa');


define('EXTRA_JS', '<script src="'.HESK_PATH.'internal-api/js/admin-ticket.js"></script><script src="'.HESK_PATH.'js/jquery.dirtyforms.min.js"></script>');

require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
require(HESK_PATH . 'inc/status_functions.inc.php');
require(HESK_PATH . 'inc/view_attachment_functions.inc.php');
require(HESK_PATH . 'inc/mail_functions.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

$modsForHesk_settings = mfh_getSettings();

require_once(HESK_PATH . 'inc/custom_fields.inc.php');


$con=@mysqli_connect('localhost', 'qube_omar', 't69xcdvu', 'qube_bellasm');
if(!$con){
	die("imposible conectarse: ".mysqli_error($con));
	}
if (@mysqli_connect_errno()) {
	die("Connect failed: ".mysqli_connect_errno()." : ". mysqli_connect_error());
	}
error_reporting(E_ALL); ini_set('display_errors', 1); 
$lati = array();
$longi = array();
$latitudes = array();
$longitudes = array();
$areas = array();
$n=0;
$query = "SELECT latitude, longitude, priority, direccion, subject, message, category FROM todd_tickets"; 
$resultado = mysqli_query($con,$query);

while ($row = mysqli_fetch_row($resultado)) {
	$latitudes[$n] 		= $row[0];//latitude
	$longitudes[$n] 	= $row[1];//longitude
	$prioridades[$n] 	= $row[2];//priority
	$direcciones[$n] 	= $row[3];//direccion
	$temas[$n] 		= $row[4];//subject
	$mensajes[$n] 		= utf8_decode(mysqli_real_escape_string($con,$row[5]));//message
	$categorias[$n] 	= $row[6];//category

	$n+=1;
	}
	$n=0;
	$resultado = hesk_dbQuery("SELECT name FROM todd_categories");
	while ($row = hesk_dbFetchAssoc($resultado)) {
		$areas[$n] = $row['name'];
		$n+=1;
	}
require_once(HESK_PATH . 'inc/headerAdmin.inc.php');
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');
?>

<script type="text/javascript">
	var var_map;
	var jsLatitudes = <?php echo json_encode($latitudes); ?>;
	window.sessionStorage.setItem('latitudesItems',JSON.stringify(jsLatitudes));
	var jsLongitudes = <?php echo json_encode($longitudes); ?>;
	window.sessionStorage.setItem('longitudesItems',JSON.stringify(jsLongitudes));
	var jsPrioridades = <?php echo json_encode($prioridades); ?>;
	window.sessionStorage.setItem('prioridadesItems',JSON.stringify(jsPrioridades));
	var jsDirecciones = <?php echo json_encode($direcciones); ?>;
	window.sessionStorage.setItem('direccionesItems',JSON.stringify(jsDirecciones));
	var jsTemas = <?php echo json_encode($temas); ?>;
	window.sessionStorage.setItem('temasItems',JSON.stringify(jsTemas));
	var jsMensajes = <?php echo json_encode($mensajes); ?>;
	window.sessionStorage.setItem('mensajesItems',JSON.stringify(jsMensajes));
	var jsCategorias = <?php echo json_encode($categorias); ?>;
	window.sessionStorage.setItem('categoriasItems',JSON.stringify(jsCategorias));
	var jsAreas = <?php echo json_encode($areas); ?>;
	window.sessionStorage.setItem('areasItems',JSON.stringify(jsAreas));
</script>

<div class="content-wrapper">
	<section class="content">
		<div class="box">
			<div class="box-header with-border">
				<h1 class="box-title">
					MAPA ARRECIFES
				</h1>
			<div class="box-tools pull-right">
				<button type="button" class="btn btn-box-tool" data-widget="collapse">
					<i class="fa fa-minus"></i>
				</button>
			</div>
		</div>
		<div class="box-body">
			<div>
				<span>MAPA</label>
				<div class="gooMap">
					<div id="g-map-canvas" style="height: 100%;"></div>
				</div>
			</div>
		</div>
	</section>
</div>

<script type="text/javascript">
	 function initMap() {
		var latitudes = JSON.parse(sessionStorage.getItem('latitudesItems'));
		var longitudes = JSON.parse(sessionStorage.getItem('longitudesItems'));
		var prioridades = JSON.parse(sessionStorage.getItem('prioridadesItems'));
		var direcciones = JSON.parse(sessionStorage.getItem('direccionesItems'));
		var temas = JSON.parse(sessionStorage.getItem('temasItems'));
		var mensajes = JSON.parse(sessionStorage.getItem('mensajesItems'));
		var categorias = JSON.parse(sessionStorage.getItem('categoriasItems'));
		var areas = JSON.parse(sessionStorage.getItem('areasItems'));
		var map = new google.maps.Map(document.getElementById('g-map-canvas'), {
		 zoom: 14,
		 center: {lat: -34.067878, lng: -60.1078219},
		mapTypeId: google.maps.MapTypeId.ROADMAP,
		mapTypeControlOptions: {
			mapTypeIds: new Array(google.maps.MapTypeId.ROADMAP, google.maps.MapTypeId.HYBRID, google.maps.MapTypeId.SATELLITE)
		},
		styles: [{"featureType":"all","elementType":"geometry.fill","stylers":[{"hue":"#00bfff"}]},
            				{"featureType":"road.highway","elementType":"geometry","stylers":[{"color":"#b9b5b5"}]},{"featureType":"water","elementType":"geometry.fill","stylers":[{"color":"#0072bc"}]}]
		});

		var infowindow = new google.maps.InfoWindow();
		var marker, i;


		for (i = 0; i < latitudes.length; i++) {  
			colorIcono='../img/mapiconos/'+prioridades[i]+'.png';
			marker = new google.maps.Marker({
				position: new google.maps.LatLng(latitudes[i], longitudes[i]),
				map: map,
				icon:colorIcono
		});
			google.maps.event.addListener(marker, 'click', (function(marker, i) {
				return function() {
					infowindow.setContent("<b>Area a Cargo:</b><br />"+areas[i]+"<br /><b>Dirección del reclamo:</b><br />"+direcciones[i]+"<br /><b>Asunto:</b><br />"+temas[i]+"<br /><b>Descripción:</b><br />"+mensajes[i]);
					infowindow.open(map, marker);
				}
			})(marker, i));
		}
	}
</script>		
<script async defer src="https://maps.googleapis.com/maps/api/js?key=AIzaSyB7b3pCSLGocsdDySVOjl-JFN_dBZSLp98&callback=initMap"></script>

<?php



require_once(HESK_PATH . 'inc/footer.inc.html');
require_once(HESK_PATH . 'inc/footer.inc.php');


/*** START FUNCTIONS ***/



?>
