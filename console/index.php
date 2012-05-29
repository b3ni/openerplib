<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8" />

		<!-- Always force latest IE rendering engine (even in intranet) & Chrome Frame
		Remove this if you use the .htaccess -->
		<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />

		<title>Console OpenERPlib</title>
		<meta name="author" content="Benito Rodriguez" />

		<meta name="viewport" content="width=device-width; initial-scale=1.0" />
	</head>
	<body>
		<div  style="margin: auto; width: 900px;">
			<header>
				<h1>Console</h1>
			</header>
			
			<form action="." method="post">
				<div>
					<h2>Configuración</h2>
					<div style="width: 450px; float: left;">
						<p><label for="bd">BD: </label><input name="bd" id="bd" value="<?php if($_POST['bd']) print $_POST['bd']; else print "bd1"?>" /></p>
						<p><label for="uid">UID: </label><input name="uid" id="uid" value="<?php if($_POST['uid']) print $_POST['uid']; else print "1"?>" /></p>	
					</div>
					<div style="width: 450px; float: left;">
						<p><label for="passwd">Password: </label><input name="passwd" id="passwd" value="<?php if($_POST['passwd']) print $_POST['passwd']; else print "bd1"?>" /></p>
						<p><label for="url">UID: </label><input name="url" id="url" value="<?php if($_POST['url']) print $_POST['url']; else print "http://localhost:8069/xmlrpc"?>" /></p>
					</div>
				</div>
	
				<div>				
						<h2>Introduce el código para ejecutar: </h2>
						<textarea style="width: 100%; height: 150px;">$open = new OpenERP($config);
$p = $open->res_partner->get(1);
print $p->id;</textarea>
						<button type="submit">Ejecutar</button>					
				</div>
			</form>
			
			<?php
				if (sizeof($_POST)) {
					include_once '../openerplib/openerplib.php';
					
					print "<h2>Resultado</h2>";
					
					$config = array(
	       				'bd'        => $_POST['bd'],
	       				'uid'       => $_POST['uid'],
	       				'passwd'    => $_POST['passwd'],
	       				'url'       => $_POST['url'],
	   				);
										
					if (!$config['bd'] OR !$config['uid'] OR !$config['passwd'] OR !$config['url']) {
						echo '<p>Configuración Inválida</p>';
					}
					
					$open = new OpenERP($config);
					
				}
			?>

			<footer>
				<p>
					&copy; Copyright by Benito Rodriguez
				</p>
			</footer>
		</div>
	</body>
</html>