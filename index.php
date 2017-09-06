<?php


define('IN_SCRIPT', 1);
define('HESK_PATH', './');
define('WYSIWYG', 1);
define('VALIDATOR', 1);

// Get all the required files and functions
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/view_attachment_functions.inc.php');

hesk_load_database_functions();
hesk_dbConnect();
// Are we in maintenance mode?
hesk_check_maintenance();

// Are we in "Knowledgebase only" mode?
hesk_check_kb_only();

$modsForHesk_settings = mfh_getSettings();

// What should we do?
$action = hesk_REQUEST('a');

switch ($action) {
	case 'add':
		hesk_session_start();
		print_add_ticket();
		break;

	case 'forgot_tid':
		hesk_session_start();
		forgot_tid();
		break;

	default:
		print_start();
}

// Print footer
require_once(HESK_PATH . 'inc/footer.inc.html');
require_once(HESK_PATH . 'inc/footer.inc.php');
exit();

/*** START FUNCTIONS ***/
function print_select_category($number_of_categories)
{
	global $hesk_settings, $hesklang;

	// Print header
	$hesk_settings['tmp_title'] = $hesk_settings['hesk_title'] . ' - ' . $hesklang['select_category'];
	require_once(HESK_PATH . 'inc/header.inc.php');

	// A categoy needs to be selected
	if (isset($_GET['category']) && empty($_GET['category']))
	{
		hesk_process_messages($hesklang['sel_app_cat'],'NOREDIRECT','NOTICE');
	}
	?>
	<ol class="breadcrumb">
		<li><a href="<?php echo $hesk_settings['site_url']; ?>"><?php echo $hesk_settings['site_title']; ?></a></li>
		<li><a href="<?php echo $hesk_settings['hesk_url']; ?>"><?php echo $hesk_settings['hesk_title']; ?></a></li>
		<li class="active"><?php echo $hesklang['submit_ticket']; ?></li>
	</ol>
	<?php
	/* This will handle error, success and notice messages */
	hesk_handle_messages();
	?>

	<div style="text-align: center">

		<h3><?php echo $hesklang['select_category_text']; ?></h3>

		<div class="select_category">
			<?php
			// Print a select box if number of categories is large
			if ($number_of_categories > $hesk_settings['cat_show_select'])
			{
				?>
				<form action="index.php" method="get">
					<select name="category" id="select_category" class="form-control">
						<?php
						if ($hesk_settings['select_cat'])
						{
							echo '<option value="">'.$hesklang['select'].'</option>';
						}
						foreach ($hesk_settings['categories'] as $k=>$v)
						{
							echo '<option value="'.$k.'">'.$v.'</option>';
						}
						?>
					</select>

					&nbsp;<br />

					<div style="text-align:center">
						<input type="submit" value="<?php echo $hesklang['c2c']; ?>" class="btn btn-default">
						<input type="hidden" name="a" value="add" />
					</div>
				</form>
				<?php
			}
			// Otherwise print quick links
			else
			{
				$new_row = 1;

				foreach ($hesk_settings['categories'] as $k=>$v):
					if ($new_row == 1) {
						echo '<div class="row">';
						$new_row = -1;
					}
				?>
					<div class="col-md-5 col-sm-10 col-md-offset-1 col-sm-offset-1">
						<a href="index.php?a=add&category=<?php echo $k; ?>" class="button-link">
							<div class="panel panel-default">
								<div class="panel-body">
									<div class="row">
										<div class="col-xs-12">
											<?php echo $v; ?>
										</div>
									</div>
								</div>
							</div>
						</a>
					</div>
				<?php
					$new_row++;
					if ($new_row == 1) {
						echo '</div>';
					}
				endforeach;
			}
			?>
		</div>
	</div>

	<?php
	return true;
} // END print_select_category()

function print_add_ticket()
{
	global $hesk_settings, $hesklang, $modsForHesk_settings;

	// Connect to the database
	hesk_load_database_functions();
	hesk_dbConnect();

	// Load custom fields
	require_once(HESK_PATH . 'inc/custom_fields.inc.php');

	// Load calendar JS and CSS
	define('CALENDAR',1);

	// Auto-focus first empty or error field
	define('AUTOFOCUS', true);

	// Pre-populate fields
	// Customer name
	if (isset($_REQUEST['name'])) {
		$_SESSION['c_name'] = $_REQUEST['name'];
	}

	// Customer email address
	if (isset($_REQUEST['email'])) {
		$_SESSION['c_email'] = $_REQUEST['email'];
		$_SESSION['c_email2'] = $_REQUEST['email'];
	}

	// Priority
	if (isset($_REQUEST['priority'])) {
		$_SESSION['c_priority'] = intval($_REQUEST['priority']);
	}

	// Subject
	if (isset($_REQUEST['subject'])) {
		$_SESSION['c_subject'] = $_REQUEST['subject'];
	}

	// Message
	if (isset($_REQUEST['message'])) {
		$_SESSION['c_message'] = $_REQUEST['message'];
	}

	// Custom fields
	foreach ($hesk_settings['custom_fields'] as $k => $v) {
		if ($v['use']==1 && isset($_REQUEST[$k])) {
			$_SESSION['c_' . $k] = $_REQUEST[$k];
		}
	}


	// Variables for coloring the fields in case of errors
	if (!isset($_SESSION['iserror'])) {
		$_SESSION['iserror'] = array();
	}

	if (!isset($_SESSION['isnotice'])) {
		$_SESSION['isnotice'] = array();
	}

	hesk_cleanSessionVars('already_submitted');

	// Tell header to load reCaptcha API if needed
	if ($hesk_settings['recaptcha_use'] == 2) {
		define('RECAPTCHA', 1);
	}

	// Get categories
	$hesk_settings['categories'] = array();
	$res = hesk_dbQuery("SELECT `id`, `name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` WHERE `type`='0' ORDER BY `cat_order` ASC");
	while ($row=hesk_dbFetchAssoc($res)) {
		$hesk_settings['categories'][$row['id']] = $row['name'];
	}

	$number_of_categories = count($hesk_settings['categories']);

	if ($number_of_categories == 0) {
		$category = 1;
	} elseif ($number_of_categories == 1) {
		$category = current(array_keys($hesk_settings['categories']));
	} else {
		$category = isset($_GET['catid']) ? hesk_REQUEST('catid'): hesk_REQUEST('category');

		// Force the customer to select a category?
		if (!isset($hesk_settings['categories'][$category])) {
			return print_select_category($number_of_categories);
		}
	}

	// Print header
	$hesk_settings['tmp_title'] = $hesk_settings['hesk_title'] . ' - ' . $hesklang['submit_ticket'];
	require_once(HESK_PATH . 'inc/header.inc.php');
	?>

	<ol class="breadcrumb">
		<li><a href="<?php echo $hesk_settings['site_url']; ?>"><?php echo $hesk_settings['site_title']; ?></a></li>
		<li><a href="<?php echo $hesk_settings['hesk_url']; ?>"><?php echo $hesk_settings['hesk_title']; ?></a></li>
		<?php if ($number_of_categories > 1) { ?>
			<li>
				<a href="index.php?a=add">
					<?php echo $hesklang['sub_support']; ?>
				</a>
			</li>
			<li class="active"><?php echo $hesk_settings['categories'][$category]; ?></li>
		<?php } else { ?>
			<li class="active"><?php echo $hesklang['sub_support']; ?></li>
		<?php } ?>
	</ol>

	<!-- START MAIN LAYOUT -->
	<?php
	$columnWidth = 'col-md-8';
	hesk_dbConnect();
	$showRs = hesk_dbQuery("SELECT `show` FROM `" . hesk_dbEscape($hesk_settings['db_pfix']) . "quick_help_sections` WHERE `id` = 1");
	$show = hesk_dbFetchAssoc($showRs);
	if (!$show['show']) {
		$columnWidth = 'col-md-10 col-md-offset-1';
	}
	?>
	<div class="row">
	<?php if ($columnWidth == 'col-md-8'): ?>
	<div align="left" class="col-md-4">
		<div class="panel panel-default">
			<div class="panel-heading"><?php echo $hesklang['quick_help']; ?></div>
			<div class="panel-body">
				<p><?php echo $hesklang['quick_help_submit_ticket']; ?></p>
			</div>
		</div>
	</div>
<?php endif; ?>
	<div class="<?php echo $columnWidth; ?>">
		<?php
		// This will handle error, success and notice messages
		hesk_handle_messages();
		?>
		<!-- START FORM -->
		<div class="form">
			<h2><?php hesk_showTopBar($hesklang['submit_ticket']); ?></h2>
			<small><?php echo $hesklang['use_form_below']; ?></small>
			<div class="blankSpace"></div>

			<div align="left" class="h3"><?php echo $hesklang['add_ticket_general_information']; ?></div>
			<div class="footerWithBorder"></div>
			<div class="blankSpace"></div>
			<?php
			$onsubmit = '';
			if ($modsForHesk_settings['rich_text_for_tickets_for_customers']) {
				$onsubmit = 'onsubmit="return validateRichText(\'message-help-block\', \'message-group\', \'message\', \''.htmlspecialchars($hesklang['this_field_is_required']).'\')"';
			}
			?>
			<form class="form-horizontal" role="form" method="post" action="submit_ticket.php?submit=1" name="form1"
				  enctype="multipart/form-data" <?php echo $onsubmit; ?>>
				<!-- Contact info -->
				<div class="form-group">
					<label for="name" class="col-sm-3 control-label"><?php echo $hesklang['name']; ?> <span
							class="important">*</span></label>

					<div class="col-sm-9">
						<input type="text" class="form-control" id="name" name="name" size="40" maxlength="30"
							   value="<?php if (isset($_SESSION['c_name'])) {
								   echo stripslashes(hesk_input($_SESSION['c_name']));
							   } ?>" <?php if (in_array('name', $_SESSION['iserror'])) {
							echo ' class="isError" ';
						} ?> placeholder="<?php echo htmlspecialchars($hesklang['name']); ?>"
							   data-error="<?php echo htmlspecialchars($hesklang['enter_your_name']); ?>" required>
						<div class="help-block with-errors"></div>
					</div>
				</div>
				<div class="form-group">
					<label for="email" class="col-sm-3 control-label"><?php echo $hesklang['email'] .
							($hesk_settings['require_email'] ? ' <span class="important">*</span>' : ''); ?></label>

					<div class="col-sm-9">
						<input type="text" class="form-control" id="email" name="email" size="40" maxlength="1000"
							   value="<?php if (isset($_SESSION['c_email'])) {
								   echo stripslashes(hesk_input($_SESSION['c_email']));
							   } ?>" <?php if (in_array('email', $_SESSION['iserror'])) {
							echo ' class="isError" ';
						} elseif (in_array('email', $_SESSION['isnotice'])) {
							echo ' class="isNotice" ';
						} ?> <?php if ($hesk_settings['detect_typos']) {
							echo ' onblur="Javascript:hesk_suggestEmail(\'email\', \'email_suggestions\', 1, 0)"';
						} ?> placeholder="<?php echo htmlspecialchars($hesklang['email']); ?>"
							   data-error="<?php echo htmlspecialchars($hesklang['enter_valid_email']); ?>" required>

						<div class="help-block with-errors"></div>
					</div>
				</div>
				<?php
				if ($hesk_settings['confirm_email']) {
					?>
					<div class="form-group">
						<label for="email2" class="col-sm-3 control-label"><?php echo $hesklang['confemail']; ?>
							<?php echo $hesk_settings['require_email'] ? ' <span class="important">*</span>' : ''; ?></label>

						<div class="col-sm-9">
							<input type="text" id="email2" class="form-control" name="email2" size="40"
								   maxlength="1000"
								   value="<?php if (isset($_SESSION['c_email2'])) {
									   echo stripslashes(hesk_input($_SESSION['c_email2']));
								   } ?>" <?php if (in_array('email2', $_SESSION['iserror'])) {
								echo ' class="isError" ';
							} ?> placeholder="<?php echo htmlspecialchars($hesklang['confemail']); ?>"
								   data-match="#email"
								   data-error="<?php echo htmlspecialchars($hesklang['confemaile']); ?>" required>

							<div class="help-block with-errors"></div>
						</div>
					</div>
					<?php
				} ?>
				<div id="email_suggestions"></div>

<!-- OMAR -->


				<div class="form-group">
					<label for="direccion" class="col-sm-3 control-label">Dirección (Calle y Número) <span class="important">*</span></label>
            				<div class="col-sm-7">
                				<input id="address" type="text" class="form-control" name="direccion" size="40" maxlength="50" value="Merlassino 816"  data-error="Ingrese calle y número o cruce de calles"
						placeholder="Av. Merlassino 816" required />
            				</div>
            				<div class="col-sm-2">
					      <input id="submit" type="button" value="Geocodificar">
            				</div>

<!--

 onChange="direccionar(this.value);"

$latlon=geolocalizar($_POST['direccion']);
$tmpvar['latitude'] = $latlon[0];//hesk_POST('latitude', 'E-4');
$tmpvar['longitude'] = $latlon[1];//hesk_POST('longitude', 'E-4');-->
		            	</div>
				<div class="form-group">
   	      		  		<label for="latitude" class="col-sm-3 control-label">Latitud</label>
            				<div class="col-sm-9">
                				<input id="formLatitud" type="text" class="form-control" name="latitude" size="40" maxlength="50" value="" readonly />
            				</div>
				</div>
				<div class="form-group">
					<label for="longitude" class="col-sm-3 control-label">Longitud</label>
					<div class="col-sm-9">
      						<input id="formLongitud" type="text" class="form-control" name="longitude" size="40" maxlength="50"	value="" readonly />
      					</div>
      				</div>


<!--
// VERSION ACTUAL CON GOOGLEMAPS E IFRAME POR OMAR ************************************************************************************************************
-->
				<div class="form-group">
					<script>var var_map;</script>
					<label for="gooMap" class="col-sm-3 control-label">Ubicación en Mapa</label>
					<div class="col-sm-9">
						<div style="width: 600px; height: 525px;">
							<div id="g-map-canvas" style="height: 100%;"></div>
						</div>
					</div>
				</div>
<!--
// VERSION ACTUAL CON GOOGLEMAPS E IFRAME POR OMAR ************************************************************************************************************
-->



				<!-- Priority -->
				<?php

				/* Can customer assign urgency? */
				if ($hesk_settings['cust_urgency']) {
					?>
					<div class="form-group">
						<label for="priority" class="col-sm-3 control-label"><?php echo $hesklang['priority']; ?> <span
								class="important">*</span></label>

						<div class="col-sm-9">
							<select id="priority" class="form-control"
									pattern="[0-9]+"
									data-error="<?php echo htmlspecialchars($hesklang['sel_app_priority']); ?>"
									name="priority" <?php if (in_array('priority', $_SESSION['iserror'])) {
								echo ' class="isError" ';
							} ?> required>
								<?php
								// Show the "Click to select"?
								if ($hesk_settings['select_pri']) {
									echo '<option value="">' . $hesklang['select'] . '</option>';
								}
								?>
								<option
									value="3" <?php if (isset($_SESSION['c_priority']) && $_SESSION['c_priority'] == 3) {
									echo 'selected="selected"';
								} ?>><?php echo $hesklang['low']; ?></option>
								<option
									value="2" <?php if (isset($_SESSION['c_priority']) && $_SESSION['c_priority'] == 2) {
									echo 'selected="selected"';
								} ?>><?php echo $hesklang['medium']; ?></option>
								<option
									value="1" <?php if (isset($_SESSION['c_priority']) && $_SESSION['c_priority'] == 1) {
									echo 'selected="selected"';
								} ?>><?php echo $hesklang['high']; ?></option>
							</select>

							<div class="help-block with-errors"></div>
						</div>
					</div>
					<?php
				}
				?>

				<!-- START CUSTOM BEFORE -->
				<?php

				/* custom fields BEFORE comments */

				$hidden_cf_buffer = '';
				foreach ($hesk_settings['custom_fields'] as $k=>$v)
				{
					if ($v['use']==1 && $v['place']==0 && hesk_is_custom_field_in_category($k, $category) )
					{
						if ($v['req']) {
							$v['req']=  '<span class="important">*</span>';
							$required_attribute = 'data-error="' . $hesklang['this_field_is_required'] . '" required';
						} else {
							$v['req'] = '';
							$required_attribute = '';
						}

						if ($v['type'] == 'checkbox')
						{
							$k_value = array();
							if (isset($_SESSION["c_$k"]) && is_array($_SESSION["c_$k"]))
							{
								foreach ($_SESSION["c_$k"] as $myCB)
								{
									$k_value[] = stripslashes(hesk_input($myCB));
								}
							}
						}
						elseif (isset($_SESSION["c_$k"]))
						{
							$k_value  = stripslashes(hesk_input($_SESSION["c_$k"]));
						}
						else
						{
							$k_value  = '';
						}

						switch ($v['type'])
						{
							/* Radio box */
							case 'radio':
								$cls = in_array($k,$_SESSION['iserror']) ? ' class="isError" ' : '';
								echo '
					<div class="form-group '.$cls.'">
						<label for="'.$k.'" class="col-sm-3 control-label">'.$v['name'].' '.$v['req'].'</label>
						<div class="col-sm-9">';

								foreach ($v['value']['radio_options'] as $option) {
									if (strlen($k_value) == 0) {
										$k_value = $option;
										$checked = empty($v['value']['no_default']) ? 'checked' : '';
									} elseif ($k_value == $option) {
										$k_value = $option;
										$checked = 'checked';
									} else {
										$checked = '';
									}

									echo '<div class="radio"><label><input type="radio" name="'.$k.'" value="'.$option.'" '.$checked.' ' . $required_attribute . '> '.$option.'</label></div>';
								}
						echo '
						<div class="help-block with-errors"></div>
						</div>
					</div>';
								break;

							/* Select drop-down box */
							case 'select':

								$cls = in_array($k,$_SESSION['iserror']) ? 'isError' : '';

								echo '
					<div class="form-group '.$cls.'">
						<label for="'.$k.'" class="col-sm-3 control-label">'.$v['name'].' '.$v['req'].'</label>
						<div class="col-sm-9">
							<select name="'.$k.'" class="form-control" ' . $required_attribute . '>';
								// Show "Click to select"?
								if ( ! empty($v['value']['show_select']))
								{
									echo '<option value="">'.$hesklang['select'].'</option>';
								}

								foreach ($v['value']['select_options'] as $option)
								{
									if ($k_value == $option)
									{
										$k_value = $option;
										$selected = 'selected';
									}
									else
									{
										$selected = '';
									}

									echo '<option '.$selected.'>'.$option.'</option>';
								}

								echo '</select>
							<div class="help-block with-errors"></div>
						</div>
					</div>';
								break;

							/* Checkbox */
							case 'checkbox':
								$cls = in_array($k,$_SESSION['iserror']) ? 'isError' : '';
								$validator = $v['req'] == '<span class="important">*</span>' ? 'data-checkbox="' . $k . '"' : '';
								$required_attribute = $validator == '' ? '' : ' data-error="' . $hesklang['this_field_is_required'] . '"';
								echo '
					<div class="form-group '.$cls.'">
						<label for="'.$k.'" class="col-sm-3 control-label">'.$v['name'].' '.$v['req'].'</label>
						<div class="col-sm-9">';

							foreach ($v['value']['checkbox_options'] as $option)
							{
								if (in_array($option,$k_value))
								{
									$checked = 'checked';
								}
								else
								{
									$checked = '';
								}

								echo '<div class="checkbox"><label><input ' . $validator . ' type="checkbox" name="'.$k.'[]" value="'.$option.'" '.$checked.' ' . $required_attribute . '> '.$option.'</label></div>';
							}
						echo '
						<div class="help-block with-errors"></div>
						</div>
					</div>';
								break;

							/* Large text box */
							case 'textarea':
								$cls = in_array($k,$_SESSION['iserror']) ? 'isError' : '';

								echo '
					<div class="form-group '.$cls.'">
						<label for="'.$k.'" class="col-sm-3 control-label">'.$v['name'].' '.$v['req'].'</label>
						<div class="col-sm-9">
							<textarea class="form-control" name="'.$k.'" rows="'.intval($v['value']['rows']).'" cols="'.intval($v['value']['cols']).'" '.$required_attribute.'>'.$k_value.'</textarea>
							<div class="help-block with-errors"></div>
						</div>
					</div>';
								break;

							// Date
							case 'date':
								if ($required_attribute != '') {
									$required_attribute .= ' pattern="[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])"';
								}

								$cls = in_array($k,$_SESSION['iserror']) ? 'isError' : '';

								echo '
					<div class="form-group '.$cls.'">
						<label for="'.$k.'" class="col-sm-3 control-label">'.$v['name'].' '.$v['req'].'</label>
						<div class="col-sm-9">
							<input type="text" name="'.$k.'" value="'.$k_value.'" class="form-control datepicker" size="10" ' . $required_attribute . '>
							<div class="help-block with-errors"></div>
						</div>
					</div>';
								break;

							// Email
							case 'email':
								$cls = in_array($k,$_SESSION['iserror']) ? 'isError' : '';

								$suggest = $hesk_settings['detect_typos'] ? 'onblur="Javascript:hesk_suggestEmail(\''.$k.'\', \''.$k.'_suggestions\', 0, 0'.($v['value']['multiple'] ? ',1' : '').')"' : '';

								echo '
					<div class="form-group '.$cls.'">
						<label for="'.$k.'" class="col-sm-3 control-label">'.$v['name'].' '.$v['req'].'</label>
						<div class="col-sm-9">
							<input type="text" name="'.$k.'" id="'.$k.'" value="'.$k_value.'" size="40" class="form-control" '.$suggest.' '.$required_attribute.'>
							<div class="help-block with-errors"></div>
						</div>
						<div id="'.$k.'_suggestions"></div>
					</div>';
								break;

							// Hidden
							case 'hidden':
								if (strlen($k_value) != 0 || isset($_SESSION["c_$k"]))
								{
									$v['value']['default_value'] = $k_value;
								}
								$hidden_cf_buffer .= '<input type="hidden" name="'.$k.'" value="'.$v['value']['default_value'].'" />';
								break;

							// Readonly
							case 'readonly':
								if (strlen($k_value) != 0 || isset($_SESSION["c_$k"]))
								{
									$v['value']['default_value'] = $k_value;
								}

								$cls = in_array($k,$_SESSION['iserror']) ? 'isError' : '';

								echo '
					<div class="form-group '.$cls.'">
						<label for="'.$k.'" class="col-sm-3 control-label">'.$v['name'].' '.$v['req'].'</label>
						<div class="col-sm-9">
							<input type="text" class="form-control white-readonly" name="'.$k.'" size="40" value="'.$v['value']['default_value'].'" readonly>
						</div>
					</div>';
								break;

							/* Default text input */
							default:
								if (strlen($k_value) != 0 || isset($_SESSION["c_$k"]))
								{
									$v['value']['default_value'] = $k_value;
								}

								$cls = in_array($k,$_SESSION['iserror']) ? 'isError' : '';

								echo '
					<div class="form-group '.$cls.'">
						<label for="'.$k.'" class="col-sm-3 control-label">'.$v['name'].' '.$v['req'].'</label>
						<div class="col-sm-9">
							<input type="text" class="form-control" name="'.$k.'" size="40" maxlength="'.intval($v['value']['max_length']).'" value="'.$v['value']['default_value'].'" '.$required_attribute.'>
							<div class="help-block with-errors"></div>
						</div>
					</div>';
						}
					}
				}

				?>
				<!-- END CUSTOM BEFORE -->

				<?php
				if ($hesk_settings['require_subject'] != -1 || $hesk_settings['require_message'] != -1) {
					?>
					<div class="blankSpace"></div>
					<div align="left" class="h3"><?php echo $hesklang['add_ticket_your_message']; ?></div>
					<div class="footerWithBorder"></div>
					<div class="blankSpace"></div>
					<!-- ticket info -->
					<?php if ($hesk_settings['require_subject'] != -1) { ?>
						<div class="form-group">
							<label for="subject" class="col-sm-3 control-label"><?php echo $hesklang['subject']; ?>
								<?php echo $hesk_settings['require_subject'] ? '<span class="important">*</span>' : ''; ?>
							</label>

							<div class="col-sm-9">
								<input type="text" id="subject" class="form-control" name="subject" size="40"
									   maxlength="40"
									   value="<?php if (isset($_SESSION['c_subject'])) {
										   echo stripslashes(hesk_input($_SESSION['c_subject']));
									   } ?>" <?php if (in_array('subject', $_SESSION['iserror'])) {
									echo ' class="isError" ';
								} ?> placeholder="<?php echo htmlspecialchars($hesklang['subject']); ?>"
									   data-error="<?php echo htmlspecialchars($hesklang['enter_subject']); ?>"
									   required>

								<div class="help-block with-errors"></div>
							</div>
						</div>
						<?php
					}
					if ($hesk_settings['require_message'] != -1) {
						?>
						<div class="form-group" id="message-group">
							<label for="message" class="col-sm-3 control-label">
								<?php echo $hesklang['message']; ?>
								<?php echo $hesk_settings['require_message'] ? '<span class="important">*</span>' : ''; ?>
							</label>
							<div class="col-sm-9">
						<textarea placeholder="<?php echo htmlspecialchars($hesklang['message']); ?>" name="message"
								  id="message" class="form-control htmlEditor" rows="12"
								  data-rich-text-enabled="<?php echo $modsForHesk_settings['rich_text_for_tickets_for_customers'];  ?>"
								  cols="60" <?php if (in_array('message', $_SESSION['iserror'])) {
							echo ' class="isError" ';
						} ?> data-error="<?php echo htmlspecialchars($hesklang['enter_message']); ?>"
								  required><?php if (isset($_SESSION['c_message'])) {
								echo stripslashes(hesk_input($_SESSION['c_message']));
							} ?></textarea>

								<div class="help-block with-errors" id="message-help-block"></div>
								<?php if ($modsForHesk_settings['rich_text_for_tickets_for_customers']): ?>
									<script type="text/javascript">
										/* <![CDATA[ */
										tinyMCE.init({
											mode: "textareas",
											editor_selector: "htmlEditor",
											elements: "content",
											theme: "advanced",
											convert_urls: false,
											plugins: "autolink",

											theme_advanced_buttons1: "cut,copy,paste,|,undo,redo,|,formatselect,fontselect,fontsizeselect,|,bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull",
											theme_advanced_buttons2: "sub,sup,|,charmap,|,bullist,numlist,|,outdent,indent,insertdate,inserttime,preview,|,forecolor,backcolor,|,hr,removeformat,visualaid,|,link,unlink,anchor,image,cleanup",
											theme_advanced_buttons3: "",

											theme_advanced_toolbar_location: "top",
											theme_advanced_toolbar_align: "left",
											theme_advanced_statusbar_location: "bottom",
											theme_advanced_resizing: true
										});
										/* ]]> */
									</script>
								<?php endif; ?>
							</div>
						</div>
						<?php
					}
				}
				?>

				<!-- START KNOWLEDGEBASE SUGGEST -->
				<?php
				if (has_public_kb() && $hesk_settings['kb_recommendanswers']) {
					?>
					<div id="kb_suggestions" style="display:none">
						<br/>&nbsp;<br/>
						<img src="img/loading.gif" width="24" height="24" alt="" border="0"
							 style="vertical-align:text-bottom"/> <i><?php echo $hesklang['lkbs']; ?></i>
					</div>

					<script language="Javascript" type="text/javascript"><!--
						hesk_suggestKB();
						//-->
					</script>
					<?php
				}
				?>
				<!-- END KNOWLEDGEBASE SUGGEST -->

				<!-- START CUSTOM AFTER -->
				<?php

				/* custom fields AFTER comments */

				foreach ($hesk_settings['custom_fields'] as $k=>$v)
				{
					if ($v['use']==1 && $v['place']==1 && hesk_is_custom_field_in_category($k, $category) )
					{
						if ($v['req']) {
							$v['req']=  '<span class="important">*</span>';
							$required_attribute = 'data-error="' . $hesklang['this_field_is_required'] . '" required';
						} else {
							$v['req'] = '';
							$required_attribute = '';
						}

						if ($v['type'] == 'checkbox')
						{
							$k_value = array();
							if (isset($_SESSION["c_$k"]) && is_array($_SESSION["c_$k"]))
							{
								foreach ($_SESSION["c_$k"] as $myCB)
								{
									$k_value[] = stripslashes(hesk_input($myCB));
								}
							}
						}
						elseif (isset($_SESSION["c_$k"]))
						{
							$k_value  = stripslashes(hesk_input($_SESSION["c_$k"]));
						}
						else
						{
							$k_value  = '';
						}

						switch ($v['type'])
						{
							/* Radio box */
							case 'radio':
								$cls = in_array($k,$_SESSION['iserror']) ? ' class="isError" ' : '';
								echo '
					<div class="form-group '.$cls.'">
						<label for="'.$k.'" class="col-sm-3 control-label">'.$v['name'].' '.$v['req'].'</label>
						<div class="col-sm-9">';

								foreach ($v['value']['radio_options'] as $option) {
									if (strlen($k_value) == 0) {
										$k_value = $option;
										$checked = empty($v['value']['no_default']) ? 'checked' : '';
									} elseif ($k_value == $option) {
										$k_value = $option;
										$checked = 'checked';
									} else {
										$checked = '';
									}

									echo '<div class="radio"><label><input type="radio" name="'.$k.'" value="'.$option.'" '.$checked.' '.$required_attribute.'> '.$option.'</label></div>';
								}
								echo '
								<div class="help-block with-errors"></div>
						</div>
					</div>';
								break;

							/* Select drop-down box */
							case 'select':

								$cls = in_array($k,$_SESSION['iserror']) ? 'isError' : '';

								echo '
					<div class="form-group '.$cls.'">
						<label for="'.$k.'" class="col-sm-3 control-label">'.$v['name'].' '.$v['req'].'</label>
						<div class="col-sm-9">
							<select name="'.$k.'" class="form-control" '.$required_attribute.'>';
								// Show "Click to select"?
								if ( ! empty($v['value']['show_select']))
								{
									echo '<option value="">'.$hesklang['select'].'</option>';
								}

								foreach ($v['value']['select_options'] as $option)
								{
									if ($k_value == $option)
									{
										$k_value = $option;
										$selected = 'selected';
									}
									else
									{
										$selected = '';
									}

									echo '<option '.$selected.'>'.$option.'</option>';
								}

								echo '</select>
							<div class="help-block with-errors"></div>
						</div>
					</div>';
								break;

							/* Checkbox */
							case 'checkbox':
								$cls = in_array($k,$_SESSION['iserror']) ? 'isError' : '';
								$validator = $v['req'] == '<span class="important">*</span>' ? 'data-checkbox="' . $k . '"' : '';
								$required_attribute = $validator == '' ? '' : ' data-error="' . $hesklang['this_field_is_required'] . '"';
								echo '
					<div class="form-group '.$cls.'">
						<label for="'.$k.'" class="col-sm-3 control-label">'.$v['name'].' '.$v['req'].'</label>
						<div class="col-sm-9">';

								foreach ($v['value']['checkbox_options'] as $option)
								{
									if (in_array($option,$k_value))
									{
										$checked = 'checked';
									}
									else
									{
										$checked = '';
									}

									echo '<div class="checkbox"><label><input ' . $validator . ' type="checkbox" name="'.$k.'[]" value="'.$option.'" '.$checked.' '.$required_attribute.'> '.$option.'</label></div>';
								}
								echo '
							<div class="help-block with-errors"></div>
						</div>
					</div>';
								break;

							/* Large text box */
							case 'textarea':
								$cls = in_array($k,$_SESSION['iserror']) ? 'isError' : '';

								echo '
					<div class="form-group '.$cls.'">
						<label for="'.$k.'" class="col-sm-3 control-label">'.$v['name'].' '.$v['req'].'</label>
						<div class="col-sm-9">
							<textarea class="form-control" name="'.$k.'" rows="'.intval($v['value']['rows']).'" cols="'.intval($v['value']['cols']).'" '.$required_attribute.'>'.$k_value.'</textarea>
							<div class="help-block with-errors"></div>
						</div>
					</div>';
								break;

							// Date
							case 'date':
								if ($required_attribute != '') {
									$required_attribute .= ' pattern="[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])"';
								}

								$cls = in_array($k,$_SESSION['iserror']) ? 'isError' : '';

								echo '
					<div class="form-group '.$cls.'">
						<label for="'.$k.'" class="col-sm-3 control-label">'.$v['name'].' '.$v['req'].'</label>
						<div class="col-sm-9">
							<input type="text" name="'.$k.'" value="'.$k_value.'" class="form-control datepicker" size="10" '.$required_attribute.'>
							<div class="help-block with-errors"></div>
						</div>
					</div>';
								break;

							// Email
							case 'email':
								$cls = in_array($k,$_SESSION['iserror']) ? 'isError' : '';

								$suggest = $hesk_settings['detect_typos'] ? 'onblur="Javascript:hesk_suggestEmail(\''.$k.'\', \''.$k.'_suggestions\', 0, 0'.($v['value']['multiple'] ? ',1' : '').')"' : '';

								echo '
					<div class="form-group '.$cls.'">
						<label for="'.$k.'" class="col-sm-3 control-label">'.$v['name'].' '.$v['req'].'</label>
						<div class="col-sm-9">
							<input type="text" name="'.$k.'" id="'.$k.'" value="'.$k_value.'" size="40" class="form-control" '.$suggest.' '.$required_attribute.'>
							<div class="help-block with-errors"></div>
						</div>
						<div id="'.$k.'_suggestions"></div>
					</div>';
								break;

							// Hidden
							case 'hidden':
								if (strlen($k_value) != 0 || isset($_SESSION["c_$k"]))
								{
									$v['value']['default_value'] = $k_value;
								}
								$hidden_cf_buffer .= '<input type="hidden" name="'.$k.'" value="'.$v['value']['default_value'].'" />';
								break;

							// Readonly
							case 'readonly':
								if (strlen($k_value) != 0 || isset($_SESSION["c_$k"]))
								{
									$v['value']['default_value'] = $k_value;
								}

								$cls = in_array($k,$_SESSION['iserror']) ? 'isError' : '';

								echo '
					<div class="form-group '.$cls.'">
						<label for="'.$k.'" class="col-sm-3 control-label">'.$v['name'].' '.$v['req'].'</label>
						<div class="col-sm-9">
							<input type="text" class="form-control white-readonly" name="'.$k.'" size="40" value="'.$v['value']['default_value'].'" readonly>
						</div>
					</div>';
								break;

							/* Default text input */
							default:
								if (strlen($k_value) != 0 || isset($_SESSION["c_$k"]))
								{
									$v['value']['default_value'] = $k_value;
								}

								$cls = in_array($k,$_SESSION['iserror']) ? 'isError' : '';

								echo '
					<div class="form-group '.$cls.'">
						<label for="'.$k.'" class="col-sm-3 control-label">'.$v['name'].' '.$v['req'].'</label>
						<div class="col-sm-9">
							<input type="text" class="form-control" name="'.$k.'" size="40" maxlength="'.intval($v['value']['max_length']).'" value="'.$v['value']['default_value'].'" ' . $required_attribute . '>
							<div class="help-block with-errors"></div>
						</div>
					</div>';
						}
					}
				}

				?>
				<!-- END CUSTOM AFTER -->

				<?php
				/* attachments */
				if ($hesk_settings['attachments']['use']) {
					?>
					<div class="form-group">
						<label for="attachments" class="col-sm-3 control-label"><?php echo $hesklang['attachments']; ?>
							:</label>

						<div align="left" class="col-sm-9">
							<?php build_dropzone_markup(); ?>
						</div>
					</div>
					<?php
					display_dropzone_field(HESK_PATH . 'internal-api/ticket/upload-attachment.php');
				}

				if ($hesk_settings['question_use'] || $hesk_settings['secimg_use'])
				{
				?>

				<!-- Security checks -->
				<?php
				if ($hesk_settings['question_use']) {
					?>
					<div class="form-group">
						<label for="question" class="col-sm-3 control-label"><?php echo $hesklang['verify_q']; ?> <span
								class="important">*</span></label>

						<?php
						$value = '';
						if (isset($_SESSION['c_question'])) {
							$value = stripslashes(hesk_input($_SESSION['c_question']));
						}
						$cls = in_array('question', $_SESSION['iserror']) ? ' class="isError" ' : '';
						echo '<div class="col-md-9">' . $hesk_settings['question_ask'] . '<br />
						<input class="form-control" id="question" type="text" name="question"
						data-error="'.htmlspecialchars($hesklang['this_field_is_required']).'"
						size="20" value="' . $value . '" ' . $cls . ' required>
						<div class="help-block with-errors"></div>
						</div>';
						?>
					</div>
					<?php
				}

				if ($hesk_settings['secimg_use'])
				{
				?>
				<div class="form-group">
					<label for="secimage" class="col-sm-3 control-label"><?php echo $hesklang['verify_i']; ?> <span
							class="important">*</span></label>
					<?php
					// SPAM prevention verified for this session
					if (isset($_SESSION['img_verified'])) {
						echo '<img src="' . HESK_PATH . 'img/success.png" width="16" height="16" border="0" alt="" style="vertical-align:text-bottom" /> ' . $hesklang['vrfy'];
					} // Not verified yet, should we use Recaptcha?
					elseif ($hesk_settings['recaptcha_use'] == 1) {
						?>
						<script type="text/javascript">
							var RecaptchaOptions = {
								theme: '<?php echo ( isset($_SESSION['iserror']) && in_array('mysecnum',$_SESSION['iserror']) ) ? 'red' : 'white'; ?>',
								custom_translations: {
									visual_challenge: "<?php echo hesk_slashJS($hesklang['visual_challenge']); ?>",
									audio_challenge: "<?php echo hesk_slashJS($hesklang['audio_challenge']); ?>",
									refresh_btn: "<?php echo hesk_slashJS($hesklang['refresh_btn']); ?>",
									instructions_visual: "<?php echo hesk_slashJS($hesklang['instructions_visual']); ?>",
									instructions_context: "<?php echo hesk_slashJS($hesklang['instructions_context']); ?>",
									instructions_audio: "<?php echo hesk_slashJS($hesklang['instructions_audio']); ?>",
									help_btn: "<?php echo hesk_slashJS($hesklang['help_btn']); ?>",
									play_again: "<?php echo hesk_slashJS($hesklang['play_again']); ?>",
									cant_hear_this: "<?php echo hesk_slashJS($hesklang['cant_hear_this']); ?>",
									incorrect_try_again: "<?php echo hesk_slashJS($hesklang['incorrect_try_again']); ?>",
									image_alt_text: "<?php echo hesk_slashJS($hesklang['image_alt_text']); ?>"
								}
							};
						</script>
						<div class="col-md-9">
							<?php
							require(HESK_PATH . 'inc/recaptcha/recaptchalib.php');
							echo recaptcha_get_html($hesk_settings['recaptcha_public_key'], null, true);
							?>
						</div>
					<?php
					}
					// Use reCaptcha API v2?
					elseif ($hesk_settings['recaptcha_use'] == 2)
					{
					?>
						<div class="col-md-9">
							<div class="g-recaptcha"
								 data-sitekey="<?php echo $hesk_settings['recaptcha_public_key']; ?>">
							</div>
						</div>
						<?php
					}
					// At least use some basic PHP generated image (better than nothing)
					else {
						$cls = in_array('mysecnum', $_SESSION['iserror']) ? ' class="isError" ' : '';

						echo '<div align="left" class="col-sm-9">';

						echo $hesklang['sec_enter'] . '<br />&nbsp;<br /><img src="print_sec_img.php?' . rand(10000, 99999) . '" width="150" height="40" alt="' . $hesklang['sec_img'] . '" title="' . $hesklang['sec_img'] . '" border="1" name="secimg" style="vertical-align:text-bottom" /> ' .
							'<a href="javascript:void(0)" onclick="javascript:document.form1.secimg.src=\'print_sec_img.php?\'+ ( Math.floor((90000)*Math.random()) + 10000);"><img src="img/reload.png" height="24" width="24" alt="' . $hesklang['reload'] . '" title="' . $hesklang['reload'] . '" border="0" style="vertical-align:text-bottom" /></a>' .
							'<br />&nbsp;<br /><input type="text" name="mysecnum" size="20" maxlength="5" ' . $cls . ' /></div>';
					}
					echo '</div>';
					}
					?>

					<?php
					}

					if ($modsForHesk_settings['request_location']):
						?>

						<div class="form-group">
							<label for="location"
								   class="col-md-3 control-label"><?php echo $hesklang['location_colon']; ?></label>

							<div class="col-sm-9">
								<p id="console"><?php echo $hesklang['requesting_location_ellipsis']; ?></p>

								<div id="map" style="height: 300px; display:none">
								</div>
							</div>
						</div>

						<!-- Submit -->
						<?php
					endif;

					if ($hesk_settings['submit_notice']) {
						?>

						<div class="row">
							<div class="col-md-12">
								<div class="alert alert-info">
									<b><?php echo $hesklang['before_submit']; ?></b>
									<ul>
										<li><?php echo $hesklang['all_info_in']; ?>.</li>
										<li><?php echo $hesklang['all_error_free']; ?>.</li>
									</ul>


									<b><?php echo $hesklang['we_have']; ?>:</b>
									<ul>
										<li><?php echo hesk_htmlspecialchars(hesk_getClientIP()) . ' ' . $hesklang['recorded_ip']; ?></li>
										<li><?php echo $hesklang['recorded_time']; ?></li>
									</ul>
								</div>
							</div>
						</div>

						<div class="row">
							<div class="col-md-9 col-md-offset-3">
								<input type="hidden" id="latitude" name="latitude" value="E-0">
								<input type="hidden" id="longitude" name="longitude" value="E-0">
								<input type="hidden" name="token" value="<?php hesk_token_echo(); ?>">
								<input type="hidden" id="screen-resolution-height" name="screen_resolution_height">
								<input type="hidden" id="screen-resolution-width" name="screen_resolution_width">
								<input type="submit" value="<?php echo $hesklang['sub_ticket']; ?>"
									   class="btn btn-default">
							</div>
						</div>
						<script>
							$('#screen-resolution-height').prop('value', screen.height);
							$('#screen-resolution-width').prop('value', screen.width);
						</script>

					<?php
					} // End IF submit_notice
					else {
					?>
						<div class=" row">
							<div class="col-md-9 col-md-offset-3">
								<input type="hidden" id="latitude" name="latitude" value="E-0">
								<input type="hidden" id="longitude" name="longitude" value="E-0">
								<input type="hidden" id="screen-resolution-height" name="screen_resolution_height">
								<input type="hidden" id="screen-resolution-width" name="screen_resolution_width">
								<input type="hidden" name="token" value="<?php hesk_token_echo(); ?>">
								<input class="btn btn-default" type="submit"
									   value="<?php echo $hesklang['sub_ticket']; ?>">
								<script>
									$('#screen-resolution-height').prop('value', screen.height);
									$('#screen-resolution-width').prop('value', screen.width);
								</script>
							</div>
						</div>

						<?php
					} // End ELSE submit_notice

					// Print custom hidden fields
					echo $hidden_cf_buffer;
					?>

					<input type="hidden" name="category" value="<?php echo $category; ?>">
					<!-- Do not delete or modify the code below, it is used to detect simple SPAM bots -->
					<input type="hidden" name="hx" value="3"/><input type="hidden" name="hy" value=""/>
					<!-- >
					<input type="text" name="phone" value="3" />
					< -->
			</form>
			<script>
				buildValidatorForTicketSubmission("form1",
					"<?php echo addslashes($hesklang['select_at_least_one_value']); ?>");
			</script>
			<script>
			var latlong = [];
			 function initMap() {
				var map = new google.maps.Map(document.getElementById('g-map-canvas'), {
				 zoom: 16,
				 center: {lat: -34.067878, lng: -60.1078219},
				mapTypeId: google.maps.MapTypeId.ROADMAP,
				mapTypeControlOptions: {
					mapTypeIds: new Array(google.maps.MapTypeId.ROADMAP, google.maps.MapTypeId.HYBRID, google.maps.MapTypeId.SATELLITE)
				},
				styles: [{"featureType":"all","elementType":"geometry.fill","stylers":[{"hue":"#00bfff"}]},
            				{"featureType":"road.highway","elementType":"geometry","stylers":[{"color":"#b9b5b5"}]},{"featureType":"water","elementType":"geometry.fill","stylers":[{"color":"#0072bc"}]}]
				});
				var geocoder = new google.maps.Geocoder();
		
				document.getElementById('submit').addEventListener('click', function() {
				 geocodeAddress(geocoder, map);
				});
			 }
		
			 function geocodeAddress(geocoder, resultsMap) {
				var address = document.getElementById('address').value;
				address = address + " Arrecifes";
				geocoder.geocode({'address': address}, function(results, status) {
				 if (status === 'OK') {
					resultsMap.setCenter(results[0].geometry.location);
					var marker = new google.maps.Marker({
					 map: resultsMap,
					 position: results[0].geometry.location
					});
					//coordenadas = results[0].geometry.location;
					document.getElementById("formLatitud").value = results[0].geometry.location.lat();
            				document.getElementById("formLongitud").value = results[0].geometry.location.lng();
				 } else {
					alert('Geocode was not successful for the following reason: ' + status);
				 }
				});
			 }
			</script>
	    <script async defer src="https://maps.googleapis.com/maps/api/js?key=AIzaSyB7b3pCSLGocsdDySVOjl-JFN_dBZSLp98&callback=initMap"></script>

		</div>
	</form>
	<?php if ($columnWidth == 'col-md-10 col-md-offset-1'): ?>
	<div class="col-md-1">&nbsp;</div></div>
<?php endif; ?>
	<!-- END FORM -->

	<?php

// Request for the users location if enabled
	if ($modsForHesk_settings['request_location']) {
		echo '
	<script>
		requestUserLocation("' . $hesklang['your_current_location'] . '", "' . $hesklang['unable_to_determine_location'] . '");
	</script>
	';
	}

	hesk_cleanSessionVars('iserror');
	hesk_cleanSessionVars('isnotice');

} // End print_add_ticket()


function print_start()
{
	global $hesk_settings, $hesklang;

	// Connect to database
	hesk_load_database_functions();
	hesk_dbConnect();

	define('PAGE_TITLE', 'CUSTOMER_HOME');

	// This will be used to determine how much space to print after KB
	$hesk_settings['kb_spacing'] = 4;

	// Include KB functionality only if we have any public articles
	has_public_kb();
	if ($hesk_settings['kb_enable'])
	{
		require(HESK_PATH . 'inc/knowledgebase_functions.inc.php');
	}
	else
	{
		$hesk_settings['kb_spacing'] += 2;
	}

	/* Print header */
	require_once(HESK_PATH . 'inc/header.inc.php');

	?>

<ol class="breadcrumb">
  <li><a href="<?php echo $hesk_settings['site_url']; ?>"><?php echo $hesk_settings['site_title']; ?></a></li>
  <li class="active"><?php echo $hesk_settings['hesk_title']; ?></li>
</ol>
	<?php
	// Service messages
	$res = hesk_dbQuery('SELECT `title`, `message`, `style`, `icon` FROM `'.hesk_dbEscape($hesk_settings['db_pfix'])."service_messages` WHERE `type`='0' ORDER BY `order` ASC");
	if (hesk_dbNumRows($res) > 0)
	{
	?>
	<div class="row">
		<div class="col-md-12">
			<?php
			while ($sm=hesk_dbFetchAssoc($res))
			{
				hesk_service_message($sm);
			}
			?>
		</div>
	</div>
	<?php } ?>
	<div class="row">
		<div class="col-md-4">
			<div class="panel panel-default">
				<div class="panel-heading"><?php echo $hesklang['view_ticket']; ?></div>
				<div class="panel-body">
					<form data-toggle="validator" role="form" class="viewTicketSidebar" action="ticket.php" method="get" name="form2">
						<div class="form-group">
							<br/>
							<label for="ticketID"><?php echo $hesklang['ticket_trackID']; ?>:</label>
							<input type="text" class="form-control" name="track" id="ticketID" maxlength="20" size="35" value=""
							 data-error="<?php echo htmlspecialchars($hesklang['enter_id']); ?>"
							placeholder="<?php echo htmlspecialchars($hesklang['ticket_trackID']); ?>" required>
							<div class="help-block with-errors"></div>
						</div>
						<?php
						$tmp = '';
						if ($hesk_settings['email_view_ticket'])
						{
							$tmp = 'document.form1.email.value=document.form2.e.value;';
						?>
						<div class="form-group">
							<label for="emailAddress"><?php echo $hesklang['email']; ?>:</label>
							<?php
							$my_email = '';
							$do_remember = '';
							if (isset($_COOKIE['hesk_myemail']))
							{
								$my_email = $_COOKIE['hesk_myemail'];
								$do_remember = 'checked';
							}
							?>
							<input type="text" class="form-control" name="e" id="emailAddress" size="35" value="<?php echo $my_email; ?>"
							data-error="<?php echo htmlspecialchars($hesklang['enter_valid_email']); ?>"
							placeholder="<?php echo htmlspecialchars($hesklang['email']); ?>" required>
							<div class="help-block with-errors"></div>
						</div>
						<div class="checkbox">
							<label for="r">
								<input type="checkbox" name="r" value="Y" <?php echo $do_remember; ?>> <?php echo $hesklang['rem_email']; ?>
							</label>
						</div>
						<?php
						}
						?>
						<input type="submit" value="<?php echo $hesklang['view_ticket']; ?>" class="btn btn-default" /><input type="hidden" name="Refresh" value="<?php echo rand(10000,99999); ?>"><input type="hidden" name="f" value="1">
					</form>
				</div>
			</div>
		</div>
		<div class="col-md-8">
				<?php
				// Print small search box
				if ($hesk_settings['kb_enable'])
				{
					hesk_kbSearchSmall();
					hesk_kbSearchLarge();
				}
				else
				{
					echo '&nbsp;';
				}
				?>
			<div class="row default-row-margins">
				<div class="col-sm-6 col-xs-12">
					<a href="index.php?a=add" class="button-link">
						<div class="panel panel-default">
							<div class="panel-body">
								<div class="row">
									<div class="col-xs-1">
										<img src="img/newTicket.png" alt="<?php echo $hesklang['sub_support']; ?>">
									</div>
									<div class="col-xs-11">
										<b><?php echo $hesklang['sub_support']; ?></b><br>
										<?php echo $hesklang['open_ticket']; ?>
									</div>
								</div>
							</div>
						</div>
					</a>
				</div>
				<div class="col-sm-6 col-xs-12">
					<a href="ticket.php" class="button-link">
						<div class="panel panel-default">
							<div class="panel-body">
								<div class="row">
									<div class="col-xs-1">
										<img src="img/viewTicket.png" alt="<?php echo $hesklang['view_existing']; ?>">
									</div>
									<div class="col-xs-11">
										<b><?php echo $hesklang['view_existing']; ?></b><br>
										<?php echo $hesklang['vet']; ?>
									</div>
								</div>
							</div>
						</div>
					</a>
				</div>
			</div>
			<div class="row default-row-margins">
			<?php
			if ($hesk_settings['kb_enable'])
			{
				?>
					<div class="col-sm-6 col-xs-12">
						<a href="knowledgebase.php" class="button-link">
							<div class="panel panel-default">
								<div class="panel-body">
									<div class="row">
										<div class="col-xs-1">
											<img src="img/knowledgebase.png" alt="<?php echo $hesklang['kb_text']; ?>">
										</div>
										<div class="col-xs-11">
											<b><?php echo $hesklang['kb_text']; ?></b><br>
											<?php echo $hesklang['viewkb']; ?>
										</div>
									</div>
								</div>
							</div>
						</a>
					</div>
			<?php } if ($modsForHesk_settings['enable_calendar'] == 1): ?>
				<div class="col-sm-6 col-xs-12">
					<a href="calendar.php" class="button-link">
						<div class="panel panel-default">
							<div class="panel-body">
								<div class="row">
									<div class="col-xs-1">
										<i class="fa fa-calendar black" style="font-size: 32px"
										   title="<?php echo $hesklang['calendar_title_case']; ?>"></i>
									</div>
									<div class="col-xs-11">
										<b><?php echo $hesklang['calendar_title_case']; ?></b><br>
										<?php echo $hesklang['calendar_index']; ?>
									</div>
								</div>
							</div>
						</div>
					</a>
				</div>
			<?php endif;

			$customNavRs = hesk_dbQuery("SELECT * FROM `" . hesk_dbEscape($hesk_settings['db_pfix']) . "custom_nav_element` AS `t1`
					INNER JOIN `" . hesk_dbEscape($hesk_settings['db_pfix']) . "custom_nav_element_to_text` AS `t2`
						ON `t1`.`id` = `t2`.`nav_element_id`
						AND `t2`.`language` = '" . hesk_dbEscape($hesk_settings['language']) . "'
					WHERE `t1`.`place` = 1");

			while ($row = hesk_dbFetchAssoc($customNavRs)):
				?>
				<div class="col-sm-6 col-xs-12">
					<a href="<?php echo $row['url']; ?>" class="button-link">
						<div class="panel panel-default">
							<div class="panel-body">
								<div class="row">
									<div class="col-xs-1">
										<?php if ($row['image_url'] !== null): ?>
											<img src="<?php echo $row['image_url']; ?>" alt="<?php echo $row['text']; ?>">
										<?php else: ?>
											<i class="<?php echo $row['font_icon']; ?> black" style="font-size: 32px"></i>
										<?php endif; ?>
									</div>
									<div class="col-xs-11">
										<b><?php echo $row['text']; ?></b><br>
										<?php echo $row['subtext']; ?>
									</div>
								</div>
							</div>
						</div>
					</a>
				</div>
			<?php endwhile; ?>
			</div>
			<?php
			if ($hesk_settings['kb_enable'])
			{
				hesk_kbTopArticles($hesk_settings['kb_index_popart']);
				hesk_kbLatestArticles($hesk_settings['kb_index_latest']);
			}
			?>
		</div>
	</div>
	<div class="blankSpace"></div>
	<div class="footerWithBorder"></div>
	<div class="blankSpace"></div>
</div>

<?php
	// Show a link to admin panel?
	if ($hesk_settings['alink'])
	{
		?>
		<p class="text-center"><a href="<?php echo $hesk_settings['admin_dir']; ?>/" ><?php echo $hesklang['ap']; ?></a></p>
		<?php
	}
	require(HESK_PATH . 'inc/footer.inc.html');

} // End print_start()


function forgot_tid()
{
global $hesk_settings, $hesklang, $modsForHesk_settings;

require(HESK_PATH . 'inc/email_functions.inc.php');

/* Get ticket(s) from database */
hesk_dbConnect();

$email = hesk_emailCleanup(hesk_validateEmail(hesk_POST('email'), 'ERR', 0)) or hesk_process_messages($hesklang['enter_valid_email'], 'ticket.php?remind=1');

if (isset($_POST['open_only'])) {
	$hesk_settings['open_only'] = $_POST['open_only'] == 1 ? 1 : 0;
}

/* Prepare ticket statuses */
$myStatusSQL = hesk_dbQuery("SELECT `ID`, `Key` FROM `" . hesk_dbEscape($hesk_settings['db_pfix']) . "statuses`");
$my_status = array();
while ($myStatusRow = hesk_dbFetchAssoc($myStatusSQL)) {
	$my_status[$myStatusRow['ID']] = $hesklang[$myStatusRow['Key']];
}

// Get tickets from the database
$res = hesk_dbQuery('SELECT * FROM `' . hesk_dbEscape($hesk_settings['db_pfix']) . 'tickets` FORCE KEY (`statuses`) WHERE ' . ($hesk_settings['open_only'] ? "`status` IN (SELECT `ID` FROM `" . hesk_dbEscape($hesk_settings['db_pfix']) . "statuses` WHERE `IsClosed` = 0) AND " : '') . ' ' . hesk_dbFormatEmail($email) . ' ORDER BY `status` ASC, `lastchange` DESC ');

$num = hesk_dbNumRows($res);
if ($num < 1) {
	if ($hesk_settings['open_only']) {
		hesk_process_messages($hesklang['noopen'],'ticket.php?remind=1&e='.rawurlencode($email));
	} else {
		hesk_process_messages($hesklang['tid_not_found'],'ticket.php?remind=1&e='.rawurlencode($email));
	}
}

$tid_list = '';
$html_tid_list = '<ul>';
$name = '';

$email_param = $hesk_settings['email_view_ticket'] ? '&e=' . rawurlencode($email) : '';

while ($my_ticket = hesk_dbFetchAssoc($res)) {
	$name = $name ? $name : hesk_msgToPlain($my_ticket['name'], 1, 0);
	$tid_list .= "
		$hesklang[trackID]: " . $my_ticket['trackid'] . "
		$hesklang[subject]: " . hesk_msgToPlain($my_ticket['subject'], 1, 0) . "
		$hesklang[status]: " . $my_status[$my_ticket['status']] . "
		$hesk_settings[hesk_url]/ticket.php?track={$my_ticket['trackid']}{$email_param}
		";

	$html_tid_list .= "<li>
		$hesklang[trackID]: " . $my_ticket['trackid'] . " <br>
		$hesklang[subject]: " . hesk_msgToPlain($my_ticket['subject'], 1, 0) . " <br>
		$hesklang[status]: " . $my_status[$my_ticket['status']] . " <br>
		$hesk_settings[hesk_url]/ticket.php?track={$my_ticket['trackid']}{$email_param}
		</li>";
}
$html_tid_list .= '</ul>';

/* Get e-mail message for customer */
$msg = hesk_getEmailMessage('forgot_ticket_id', '', $modsForHesk_settings, 0, 0, 1);
$msg = processEmail($msg, $name, $num, $tid_list);

// Get HTML message for customer
$htmlMsg = hesk_getHtmlMessage('forgot_ticket_id', '', $modsForHesk_settings, 0, 0, 1);
$htmlMsg = processEmail($htmlMsg, $name, $num, $html_tid_list);


$subject = hesk_getEmailSubject('forgot_ticket_id');

/* Send e-mail */
hesk_mail($email, $subject, $msg, $htmlMsg, $modsForHesk_settings);

/* Show success message */
$tmp = '<b>' . $hesklang['tid_sent'] . '!</b>';
$tmp .= '<br />&nbsp;<br />' . $hesklang['tid_sent2'] . '.';
$tmp .= '<br />&nbsp;<br />' . $hesklang['check_spambox'];
hesk_process_messages($tmp, 'ticket.php?e=' . $email, 'SUCCESS');
exit();

/* Print header */
$hesk_settings['tmp_title'] = $hesk_settings['hesk_title'] . ' - ' . $hesklang['tid_sent'];
require_once(HESK_PATH . 'inc/header.inc.php');
?>

<ol class="breadcrumb">
	<li><a href="<?php echo $hesk_settings['site_url']; ?>"><?php echo $hesk_settings['site_title']; ?></a></li>
	<li><a href="<?php echo $hesk_settings['hesk_url']; ?>"><?php echo $hesk_settings['hesk_title']; ?></a></li>
	<li class="active"><?php echo $hesklang['tid_sent']; ?></li>
</ol>
<tr>
	<td>

		<?php

		} // End forgot_tid()

		function processEmail($msg, $name, $num, $tid_list) {
			global $hesk_settings;

			$msg = str_replace('%%NAME%%', $name, $msg);
			$msg = str_replace('%%NUM%%', $num, $msg);
			$msg = str_replace('%%LIST_TICKETS%%', $tid_list, $msg);
			$msg = str_replace('%%SITE_TITLE%%', hesk_msgToPlain($hesk_settings['site_title'], 1), $msg);
			$msg = str_replace('%%SITE_URL%%', $hesk_settings['site_url'], $msg);
			return $msg;
		}

function has_public_kb($use_cache=1) {
	global $hesk_settings;

	// Return if KB is disabled
	if ( ! $hesk_settings['kb_enable']) {
		return 0;
	}

	// Do we have a cached version available
	$cache_dir = $hesk_settings['cache_dir'].'/';
	$cache_file = $cache_dir . 'kb.cache.php';

	if ($use_cache && file_exists($cache_file)) {
		require($cache_file);
		return $hesk_settings['kb_enable'];
	}

	// Make sure we have database connection
	hesk_load_database_functions();
	hesk_dbConnect();

	// Do we have any public articles at all?
	$res = hesk_dbQuery("SELECT `t1`.`id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."kb_articles` AS `t1`
						LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."kb_categories` AS `t2` ON `t1`.`catid` = `t2`.`id`
						WHERE `t1`.`type`='0' AND `t2`.`type`='0' LIMIT 1");

	// If no public articles, disable the KB functionality
	if (hesk_dbNumRows($res) < 1) {
		$hesk_settings['kb_enable'] = 0;
	}

	// Try to cache results
	if ($use_cache && (is_dir($cache_dir) || (@mkdir($cache_dir, 0777) && is_writable($cache_dir)))) {
		// Is there an index.htm file?
		if ( ! file_exists($cache_dir.'index.htm')) {
			@file_put_contents($cache_dir.'index.htm', '');
		}

		// Write data
		@file_put_contents($cache_file, '<?php if (!defined(\'IN_SCRIPT\')) {die();} $hesk_settings[\'kb_enable\']=' . $hesk_settings['kb_enable'] . ';' );
	}

	return $hesk_settings['kb_enable'];

} // End has_public_kb()