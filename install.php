<?php
	/**
	 * Declare default variables.
	 */

	$defaultSiteName = 'MageLink';

	//Get the default site URL through the $_SERVER['SERVER_NAME'] and $_SERVER['REQUEST_URI'] variables.
	$defaultsiteUrl = sprintf("//%s%s",
		filter_input(INPUT_SERVER, 'SERVER_NAME', FILTER_SANITIZE_URL), //Sanitize using FILTER_SANITIZE_URL for added security.
		filter_input(INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL)
	);

	$errors = array();

	//If the form was submitted (input name="submitted")
	if (isset($_POST['submitted'])) {
		//Get the site name by filtering the $_POST['siteName'] variable and sanitizing the string.
		$siteName = filter_input(INPUT_POST, 'siteName', FILTER_SANITIZE_STRING);

		//If the site name is empty, set it to the default site name.
		if (empty($siteName)) {
			$siteName = $defaultSiteName;
		}

		//Get the site url by filtering the $_POST['siteUrl'] variable and sanitizing the url.
		$siteUrl = filter_input(INPUT_POST, 'siteUrl', FILTER_SANITIZE_URL);

		//If the site url is empty, add the human readable error to the errors array.
		if (empty($siteUrl)) {
			$errors[] = 'You must provide a base site URL.';
		}

		//Get the optional hcaptcha integration.
		$hcaptchaSiteKey = filter_input(INPUT_POST, 'hcaptchaSiteKey', FILTER_SANITIZE_STRING);
		$hcaptchaSecretKey = filter_input(INPUT_POST, 'hcaptchaSecretKey', FILTER_SANITIZE_STRING);

		$hostname = filter_input(INPUT_POST, 'hostname', FILTER_SANITIZE_URL);

		if (empty($hostname)) {
			$errors[] = 'You must provide a database hostname.';
		}

		$port = filter_input(INPUT_POST, 'port', FILTER_SANITIZE_URL);

		if (empty($port)) {
			$errors[] = 'You must provide a database port.';
		}

		$database = filter_input(INPUT_POST, 'database', FILTER_SANITIZE_STRING);

		if (empty($database)) {
			$errors[] = 'You must provide a database name.';
		}

		$username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);

		if (empty($username)) {
			$errors[] = 'You must provide a database username.';
		}

		$password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);

		//If the errors array is empty, meaning no errors were found.
		if (empty($errors)) {
			//Use a try-catch block to catch errors to inform the user what might be wrong.
			try {
				//Declare the DSN, or data source name, which contains instructions to connect to the database.
				$dsn = "mysql:host=$hostname;port=$port;dbname=$database";

				//Connect to the database with additional configurations.
				$db = new PDO($dsn, $username, $password, array(
					PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, //Set the error mode to exception.
					PDO::ATTR_EMULATE_PREPARES => false //Set emulate prepares to false, which gives us errors at prepare-time to satisfy the error mode.
				));

				#Use Heredoc syntax to write out the configuration file.
				$config = <<<EOT
<?php
	\$config = array(
		'app' => array(
			'name' => "$siteName",
			'url' => "$siteUrl",
			'hcaptchaSiteKey' => "$hcaptchaSiteKey",
			'hcaptchaSecretKey' => "$hcaptchaSecretKey",
			'lastUpdated' => 15
		),
		'db' => array(
			'hostname' => "$hostname",
			'port' => "$port",
			'database' => "$database",
			'username' => "$username",
			'password' => "$password",
		)
	);
?>
EOT;

				#Create the configuration file.
				touch(sprintf("%s/config.php", __DIR__));

				//Attempt to put the contents in the configuration file.
				file_put_contents(sprintf("%s/config.php", __DIR__), $config);

				//Get all of the schema files.
				$schema = array_diff(scandir(sprintf("%s/schema", __DIR__)), array('..', '.'));

				//For each schema file.
				foreach($schema as $filename) {
					//Get the contents as SQL.
					$sql = file_get_contents(sprintf("%s/schema/%s", __DIR__, $filename));

					//Prepare the SQL.
					$statement = $db -> prepare($sql);

					//Execute the SQL.
					$statement -> execute();
				}

				//Redirect to the site URL.
				header("Location: $siteUrl");
			} catch(PDOException | ErrorException | LogicException $e) {
				$errors[] = $e -> getMessage();
			}
		}
	}
?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title>Install MageLink</title>
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css" />
	</head>
	<body>
		<main class="section">
			<header class="container">
				<h1 class="title mb-5">Install MageLink</h1>
			</header>
			<section class="container">
<?php if (!empty($errors)) { ?>
	<?php foreach($errors as $error) { ?>
				<div class="notification is-danger">
					<?php print($error); ?>
				</div>
	<?php } ?>
<?php } ?>
				<form method="POST">
					<h2 class="subtitle">Application Settings</h2>
					<div class="field">
						<label class="label" for="siteName">
							Site Name
							<span class="has-text-danger" title="required">*</span>
						</label>
						<div class="control">
							<input id="siteName" class="input" type="text" name="siteName"
								value="<?php print($siteName ?? $defaultSiteName); ?>" />
						</div>
					</div>
					<div class="field">
						<label class="label" for="siteUrl">
							Site URL
							<span class="has-text-danger" title="required">*</span>
						</label>
						<div class="control">
							<input id="siteUrl" class="input" type="text" name="siteUrl"
								value="<?php print($siteUrl ?? $defaultsiteUrl); ?>" />
						</div>
					</div>
					<div class="field">
						<label class="label" for="hcaptchaSiteKey">hCaptcha Site Key (optional)</label>
						<div class="control">
							<input id="hcaptchaSiteKey" class="input" type="text" name="hcaptchaSiteKey"
								value="<?php print($hcaptchaSiteKey ?? null); ?>" />
						</div>
					</div>
					<div class="field">
						<label class="label" for="hcaptchaSecretKey">hCaptcha Secret Key (optional)</label>
						<div class="control">
							<input id="hcaptchaSecretKey" class="input" type="text" name="hcaptchaSecretKey"
								value="<?php print($hcaptchaSecretKey ?? null); ?>" />
						</div>
					</div>
					<h2 class="subtitle">Database Settings</h2>
					<div class="field">
						<label class="label" for="hostname">
							Database Hostname
							<span class="has-text-danger" title="required">*</span>
						</label>
						<div class="control">
							<input id="hostname" class="input" type="text" name="hostname"
								value="<?php print($hostname ?? 'localhost'); ?>" />
						</div>
					</div>
					<div class="field">
						<label class="label" for="port">
							Database Port
							<span class="has-text-danger" title="required">*</span>
						</label>
						<div class="control">
							<input id="port" class="input" type="number" name="port"
								value="<?php print($port ?? 3306); ?>" />
						</div>
					</div>
					<div class="field">
						<label class="label" for="database">
							Database Name
							<span class="has-text-danger" title="required">*</span>
						</label>
						<div class="control">
							<input id="database" class="input" type="text" name="database" placeholder="magelink"
								value="<?php print($database ?? null); ?>" />
						</div>
					</div>
					<div class="field">
						<label class="label" for="username">
							Database Username
							<span class="has-text-danger" title="required">*</span>
						</label>
						<div class="control">
							<input id="username" class="input" type="text" name="username" placeholder="root"
								value="<?php print($username ?? null); ?>" />
						</div>
					</div>
					<div class="field">
						<label class="label" for="password">Database Password</label>
						<div class="control">
							<input id="password" class="input" type="password" name="password"
								value="<?php print($password ?? null); ?>" />
						</div>
					</div>
					<input type="submit" class="button is-primary" name="submitted" value="Install" />
				</form>
			</section>
			<footer class="section">
				<section class="container has-text-centered">
					Powered by <a href="https://github.com/mookman288/magelink" target="_blank" rel="noopener">MageLink</a>
				</section>
			</footer>
		</main>
	</body>
</html>