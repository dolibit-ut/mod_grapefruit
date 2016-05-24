<?php
	require '../config.php';
	
	dol_include_once('/grapefruit/lib/grapefruit.lib.php');
	
	$langs->load("agenda");
	$langs->load("other");

	$get=GETPOST('get');





	switch ($get) {
		case 'fullcalandar_tasks':
			$projectid = GETPOST('projectid');
			return grapefruitGetTasksForProject('fk_task', -1, 0, $projectid);
			
			break;
			
		default:
			break;
	}

