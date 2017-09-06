<?php

header('Content-Type: text/html; charset=UTF-8');  

define('IN_SCRIPT', 1);
define('HESK_PATH', '../');
define('WYSIWYG', 1);
define('VALIDATOR', 1);
define('MFH_PAGE_LAYOUT', 'TOP_ONLY');
define('PAGE_TITLE', 'informePresidenteMapa');


define('EXTRA_JS', '<script src="'.HESK_PATH.'internal-api/js/admin-ticket.js"></script><script src="'.HESK_PATH.'js/jquery.dirtyforms.min.js"></script>');

/* Get all the required files and functions */
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

// Load custom fields
require_once(HESK_PATH . 'inc/custom_fields.inc.php');


$con=@mysqli_connect('localhost', 'qube_omar', 't69xcdvu', 'qube_toddsop');
//$con=@mysqli_connect('localhost', 'jorge_alerta', 'j0rg3diem3rcK', 'jorge_alerta');
if(!$con){
	die("imposible conectarse: ".mysqli_error($con));
	}
if (@mysqli_connect_errno()) {
	die("Connect failed: ".mysqli_connect_errno()." : ". mysqli_connect_error());
	}
error_reporting(E_ALL); ini_set('display_errors', 1); 
/* Get ticket info */
		$lati = array();
		$longi = array();
		$latitudes = array();
		$longitudes = array();
		$areas = array();
		/* Get ticket info */
		$n=0;
		$query = "SELECT latitude, longitude, priority, direccion, subject, message, category FROM todd_tickets"; 
		$resultado = mysqli_query($con,$query);
/* cambiar el conjunto de caracteres a utf8 */
//	mysqli_query("SET NAMES 'UTF8'");
//		$resultado = hesk_dbQuery("SELECT latitude, longitude, priority, direccion, subject, message, category FROM todd_tickets");
		while ($row = mysqli_fetch_row($resultado)) {
				$latitudes[$n] 		= $row[0];//latitude
				$longitudes[$n] 	= $row[1];//longitude
				$prioridades[$n] 	= $row[2];//priority
				$direcciones[$n] 	= $row[3];//direccion
				$temas[$n] 		= $row[4];//subject
				$mensajes[$n] 		= utf8_decode(mysqli_real_escape_string($con,$row[5]));//message
				$categorias[$n] 	= $row[6];//category
//echo '<br /><br /><br />reputamadre '.$n.'  '.$row[5];
				$n+=1;
		}
		$n=0;
		$resultado = hesk_dbQuery("SELECT name FROM todd_categories");
		while ($row = hesk_dbFetchAssoc($resultado)) {
				$areas[$n] = $row['name'];
				$n+=1;
}
/*		echo '<br /><br /><br /><br /><br />putamadre '.json_encode($latitudes);
		echo '<br />putamadre '.json_encode($longitudes);
		echo '<br />putamadre '.json_encode($prioridades);
		echo '<br />putamadre '.json_encode($direcciones);
		echo '<br />putamadre '.json_encode($temas);
		echo '<br />putamadre '.json_encode($mensajes);
		echo '<br />putamadre '.json_encode($categorias);
*/
/* Print header */
require_once(HESK_PATH . 'inc/headerAdmin.inc.php');
/* Print admin navigation */
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
					<iframe name="mapita2" src="../mapaGoogle3.html" width="100%" height="100%" frameborder="0"></iframe>
				</div>
			</div>
		</div>
	</section>
</div>
<?php

require_once(HESK_PATH . 'inc/footer.inc.php');


/*** START FUNCTIONS ***/



?>
