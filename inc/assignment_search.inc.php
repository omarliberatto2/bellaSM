<?php


/* Check if this is a valid include */
if (!defined('IN_SCRIPT')) {die('Invalid attempt');}

/* Assignment */
// -> SELF
$s_my[$fid] = empty($_GET['s_my']) ? 0 : 1;
// -> OTHERS
$s_ot[$fid] = empty($_GET['s_ot']) ? 0 : 1;
// -> UNASSIGNED
$s_un[$fid] = empty($_GET['s_un']) ? 0 : 1;

// -> Setup SQL based on selected ticket assignments

/* Make sure at least one is chosen */
if ( ! $s_my[$fid] && ! $s_ot[$fid] && ! $s_un[$fid])
{
	$s_my[$fid] = 1;
	$s_ot[$fid] = 1;
	$s_un[$fid] = 1;
	if (!defined('MAIN_PAGE'))
	{
		hesk_show_notice($hesklang['e_nose']);
	}
}

/* If the user doesn't have permission to view assigned to others block those */
if ( ! hesk_checkPermission('can_view_ass_others',0))
{
	$s_ot[$fid] = 0;
}

/* If the user doesn't have permission to view unassigned tickets block those */
if ( ! hesk_checkPermission('can_view_unassigned',0))
{
	$s_un[$fid] = 0;
}

/* Process assignments */
if ( ! $s_my[$fid] || ! $s_ot[$fid] || ! $s_un[$fid])
{
	if ($s_my[$fid] && $s_ot[$fid])
    {
    	// All but unassigned
    	$sql .= " AND `owner` > 0 ";
    }
    elseif ($s_my[$fid] && $s_un[$fid])
    {
    	// My tickets + unassigned
    	$sql .= " AND `owner` IN ('0', '" . intval($_SESSION['id']) . "') ";
    }
    elseif ($s_ot[$fid] && $s_un[$fid])
    {
    	// Assigned to others + unassigned
    	$sql .= " AND `owner` != '" . intval($_SESSION['id']) . "' ";
    }
    elseif ($s_my[$fid])
    {
    	// Assigned to me only
    	$sql .= " AND `owner` = '" . intval($_SESSION['id']) . "' ";
    }
    elseif ($s_ot[$fid])
    {
    	// Assigned to others
    	$sql .= " AND `owner` NOT IN ('0', '" . intval($_SESSION['id']) . "') ";
    }
    elseif ($s_un[$fid])
    {
    	// Only unassigned
    	$sql .= " AND `owner` = 0 ";
    }
}
