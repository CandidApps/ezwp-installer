<?php 

/*

The MIT License (MIT)

Copyright (c) 2013 Matt Riggio (https://github.com/rags02)

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

*/

error_reporting(0); 

/**
* Trying to keep this in one file, so the actual code is a bit... long.
*
* Installs WordPress.
*
*/


/** Functions 
-----------------------------------------------------------------------------*/

// Credit: http://www.php.net/manual/en/function.copy.php#91010
function recurse_copy($src,$dst) {
    $dir = opendir($src);
    @mkdir($dst);
    while(false !== ( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
            if ( is_dir($src . '/' . $file) ) {
                recurse_copy($src . '/' . $file,$dst . '/' . $file);
            }
            else {
                copy($src . '/' . $file,$dst . '/' . $file);
            }
        }
    }
    closedir($dir);
} 

// Credit: http://stackoverflow.com/a/13490957
function rrmdir($dir) { 
	foreach(glob($dir . '/*') as $file) { 
		if(is_dir($file)) rrmdir($file); else unlink($file); 
	} rmdir($dir); 
}


/** AJAX Processing
-----------------------------------------------------------------------------*/ 

define( 'DS',       DIRECTORY_SEPARATOR ); 
define( 'ABSPATH', dirname(__FILE__) );

if ( isset( $_POST['action'] ) ) {
	
	switch ( $_POST['action'] ) {
		
		case 'sys_check':
			// Got the tools?
			if ( !class_exists( 'PharData' ) )
				echo json_encode( array( 0, '<label class="label label-important">Error!</label> You need PharData (PHP 5.3+).' ) );
			
			echo json_encode( array( 1, '<label class="label label-success">Complete!</label> Looks like you have got what it takes.' ) );
			break;
		
		case 'download': 
			// Grab WordPress
			if ( ! file_exists( 'latest.tar.gz' ) )
				file_put_contents( 'latest.tar.gz', fopen( 'http://wordpress.org/latest.tar.gz', 'r') ); 
			
			echo json_encode( array( 1, '<label class="label label-success">Complete!</label> Downloaded the most recent version of WordPress.' ) );
			break;
			
		case 'decompress': 
			$wpc = new PharData( 'latest.tar.gz' );
			$wpc->decompress();
			
			echo json_encode( array( 1, '<label class="label label-success">Complete!</label> Decompressed WordPress.' ) );
			break;
			
		case 'extract': 
			$wp = new PharData( 'latest.tar' );
			$wp->extractTo( './' );
			
			echo json_encode( array( 1, '<label class="label label-success">Complete!</label> Extracted (unpacked) all the WordPress files.' ) );
			break;
			
		case 'move': 
			recurse_copy( ABSPATH . DS . 'wordpress', ABSPATH );
			
			echo json_encode( array( 1, '<label class="label label-success">Complete!</label> Moved all the WordPress files to this directory.' ) );
			break;
			
		case 'delete_wp':
			$dir = ABSPATH . DS . 'wordpress';

			rrmdir( $dir );
			
			echo json_encode( array( 1, '<label class="label label-success">Complete!</label> Deleted the extra WordPress folder we do not need.' ) );
			break;
			
		case 'delete_gz': 
			unlink( 'latest.tar.gz' );
			
			echo json_encode( array( 1, '<label class="label label-success">Complete!</label> Deleted the compressed WordPress download.' ) );
			break;
			
		case 'delete_tar': 
			unset( $wp );
			unlink( 'latest.tar' );
			
			$also = ''; 
			
			if ( $_POST['wpdb'] == 1 ) { 
				
				$data = array( 
					'dbname' => $_POST['settings']['dbname'], 
					'uname'  => $_POST['settings']['uname'], 
					'pwd'    => $_POST['settings']['pwd'], 
					'dbhost' => $_POST['settings']['dbhost'], 
					'prefix' => $_POST['settings']['prefix']
				);
				
				// Try to validate connection using supplied credentials 
				if ( class_exists('PDO') ) {
				
					// ... optimally with PDO				
					try {
						$dbh = new PDO('mysql:host=' . $data['dbhost'] . ';dbname=' . $data['dbname'], $data['uname'], $data['pwd']);
					} catch (PDOException $e) {
						echo json_encode( array( 0, '<label class="label label-important">Error!</label> Could not connect to the database with what you provided (using PDO).' ) );
						die();
					}
					
				} else {
				
					// ... for environments that aren't up on deprecation in PHP 5.5.0
					$link = mysql_connect( $data['dbhost'], $data['uname'], $data['pwd'] );
					if ( !$link ) {
						echo json_encode( array( 0, '<label class="label label-important">Error!</label> Could not connect to the database with what you provided (using mysql_connect).' ) );
						die();
					}
				}
				
				// If applicable...
				$url = str_replace( 'wpi.php', '', 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ) . 'wp-admin/setup-config.php?step=2';

				$options = array(
					'http' => array(
						'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
						'method'  => 'POST',
						'content' => http_build_query($data),
					),
				);
				$context  = stream_context_create($options);
				$result   = file_get_contents($url, false, $context);
				
				// We make a lot of assumptions here... 
				if ( !strstr( $result, 'sparky' ) ) {
					echo json_encode( array( 0, '<label class="label label-important">Error!</label> Could not create the WordPress wp-config.php file.' ) );
					die();
				}
				
				// ... continue 
				
				$also = 'Your <span class="label">wp-config.php</span> file was created.  We are also going to delete this file so you have a nice <em>clean</em> directory. Here we go!';
				
			}
			
			$db_good = ( $also == "" ) ? 1 : 2; 
			
			echo json_encode( array( $db_good, '<label class="label label-success">Complete!</label> Closed the WordPress archive and deleted it. ' . $also ) );
			break;
			
		case 'redirect':		
			unlink('wpi.php'); 
			
			echo json_encode( array( 3 ) );
			break; 
			
	} // End switch
	
	die();
}

?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<title>WPI (WordPress-Installer)</title>
		
		<link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/2.3.2/css/bootstrap.min.css" rel="stylesheet">
		
		<style>
			/* CSS */
			
		</style>
		
		<script>
			var ajaxurl = './wpi.php'; 
			
			// Found this: http://i.stack.imgur.com/FhHRx.gif
			// Here: http://stackoverflow.com/a/1964871
			var aloader = 'http://i.stack.imgur.com/FhHRx.gif';
		</script>
		
	</head>

	<body>
		
		<div class="container panel">
			<div class="page-header">
				<h1>Install WordPress <small>for "most" Shared Hosting running PHP 5.3+</small></h1>
			</div>			
			
			<p>You will be installing WordPress in the following directory path (if you don't want it here, move the <label class="label">wpi.php</label> to the correct directory and navigate to it in your browser):</p>
			
			<h3><?php echo dirname(__FILE__) ?></h3>
			
			<form class="form-horizontal" id="wp_db_form">
				<label class="checkbox">
					<input type="checkbox" id="db_settings"> Configure Database too
				</label>
				
				<fieldset style="display: none;" id="db_form">
					<legend>WordPress Database Configuration</legend>
					
					<div class="control-group">
						<label class="control-label" for="dbname">Database Name</label>
						<div class="controls">
							<input type="text" id="dbname" name="dbname" placeholder="Database Name">
						</div>
					</div>
					
					<div class="control-group">
						<label class="control-label" for="uname">User Name</label>
						<div class="controls">
							<input type="text" id="uname" name="uname" placeholder="User Name">
						</div>
					</div>
					
					<div class="control-group">
						<label class="control-label" for="pwd">Password</label>
						<div class="controls">
							<input type="text" id="pwd" name="pwd" placeholder="Password">
						</div>
					</div>
					
					<div class="control-group">
						<label class="control-label" for="dbhost">Database Host</label>
						<div class="controls">
							<input type="text" id="dbhost" name="dbhost" placeholder="Database Host" value="localhost">
						</div>
					</div>
					
					<div class="control-group">
						<label class="control-label" for="prefix">Table Prefix</label>
						<div class="controls">
							<input type="text" id="prefix" name="prefix" placeholder="Table Prefix" value="wp_">
						</div>
					</div>
				</fieldset>
			</form>
			
			<button type="button" id="install" class="btn btn-primary">Install WordPress</button>
			
			<table class="table" style="display: none;" id="wp_progress">
				<thead>
					<tr>
						<th>Status</th>
						<th>Description</th>
					</tr>
				</thead>
				<tbody>
					<tr id="sys_check">
						<td class="status"><img class="loader"></td>
						<td class="desc">Checking your system and this script's requirements...</td>
					</tr>
					<tr id="download" style="display: none;">
						<td class="status"><img class="loader"></td>
						<td class="desc">Downloading the latest version of WordPress...</td>
					</tr>
					<tr id="decompress" style="display: none;">
						<td class="status"><img class="loader"></td>
						<td class="desc">Decompressing the <label class="label">.gz</label> file...</td>
					</tr>
					<tr id="extract" style="display: none;">
						<td class="status"><img class="loader"></td>
						<td class="desc">Unpacking the <label class="label">.tar</label> file...</td>
					</tr>
					<tr id="move" style="display: none;">
						<td class="status"><img class="loader"></td>
						<td class="desc">Moving all the WordPress files and folders to this directory...</td>
					</tr>
					<tr id="delete_wp" style="display: none;">
						<td class="status"><img class="loader"></td>
						<td class="desc">Deleting the no longer needed WordPress folder we downloaded...</td>
					</tr>
					<tr id="delete_gz" style="display: none;">
						<td class="status"><img class="loader"></td>
						<td class="desc">Deleting the <label class="label">.gz</label> file...</td>
					</tr>
					<tr id="delete_tar" style="display: none;">
						<td class="status"><img class="loader"></td>
						<td class="desc">Deleting the <label class="label">.tar</label> file <strong>and</strong> checking your WordPress database settings (if applicable)...</td>
					</tr>
					<tr id="redirect" style="display: none;">
						<td class="status"><img class="loader"></td>
						<td class="desc">Redirecting you to the WordPress start page...</td>
					</tr>
				</tbody>
			</table>
		</div>		

		<!-- Javascript (http://cdnjs.com/)
		================================================== -->
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.10.1/jquery.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/2.3.2/js/bootstrap.min.js"></script>
		
		<script>
			var wpSteps = [
				'sys_check', 'download', 'decompress', 'extract', 'move', 'delete_wp', 'delete_gz', 'delete_tar', 'redirect'
			], 
				wpSettings = {}, 
				wpRedirect = "wp-admin/setup-config.php"; 
			
			function wp( step ) {
				
				$('#' + wpSteps[step]).show(); 
				
				var settings = ( wpSteps[step] == 'delete_tar' ) ? wpSettings : false, 
					wpdb     = ( $('#db_settings').is(':checked') ) ? 1 : 0;
					
				$.ajax({
					type:     "POST", 
					url:      ajaxurl, 
					dataType: 'json', 
					data:    { 
						action:   wpSteps[step], 
						settings: settings,
						wpdb:     wpdb,
						_rand:    Math.random() 
					}
				})
				.done(function( response ) {
					
					if ( response[0] == 1 ) {
					
						$('#' + wpSteps[step] + ' .status').html( '<i class="icon-ok"></i>' ); 
						$('#' + wpSteps[step] + ' .desc').html( response[1] ); 
						
						step++; 
						wp( step );
						
					} else if ( response[0] == 2 ) {
					
						$('#' + wpSteps[step] + ' .status').html( '<i class="icon-ok"></i>' ); 
						$('#' + wpSteps[step] + ' .desc').html( response[1] ); 
						
						wpRedirect = "wp-admin/install.php";
						
						step++; 
						wp( step );
						
					} else if ( response[0] == 3 ) {
						
						setTimeout( function() { window.location.assign( wpRedirect ); }, 2500 );
						
					} else {
					
						$('#' + wpSteps[step] + ' .status').html( '<i class="icon-remove"></i>' ); 
						$('#' + wpSteps[step] + ' .desc').html( response[1] ); 					
					
					}
					
					
					
				});
			
			}
			
			$(document).ready( function() {
				
				// Our loading images 
				$('.loader').attr( 'src', aloader ); 
				
				// Settings 
				$('#db_settings').on( 'click', function() {
					$('#db_form').toggle();
				}); 
				
				$('#install').on( 'click', function(e) {
					e.preventDefault(); 
					
					$(this).addClass('disabled').hide();
					
					$('#wp_progress').show(); 
					$('#wp_db_form').hide(); 
					
					// WP Settings?
					if ( $('#db_settings').is(':checked') ) {
						wpSettings['dbname'] = $('#dbname').val(); 
						wpSettings['uname']  = $('#uname').val(); 
						wpSettings['pwd']    = $('#pwd').val(); 
						wpSettings['dbhost'] = $('#dbhost').val(); 
						wpSettings['prefix'] = $('#prefix').val();
					}
					
					// WordPress loop installer
					setTimeout( function() { wp( 0 ); }, 1500 );
					
				});
				
			});
		
		</script>
	</body>
</html>