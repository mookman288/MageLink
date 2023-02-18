<?php

	/**
	 * Generates a code based on a collection of characters and a length.
	 *
	 * Function based on ChatGPT output.
	 *
	 * @param array $characters
	 * @param integer $length
	 * @return string
	 */
	function generateCode($characters = array(), $length = 1) {
		//Declare the code variable as an empty string.
		$code = '';

		//Generate a cryptographically secure string of bytes at the length supplied and then convert it to an array.
		$random = str_split(random_bytes($length));

		//Loop for the length the code should be.
		for ($i = 0; $i < $length; $i++) {
			//Get the integer equivalent of the byte for this iteration from the random collection.
			$integer = ord($random[$i]); //Example: 95 could be the number output here.

			//Get a valid index from our character set by using the modulus operator to get the remainder of divison.
			$index = $integer % count($characters); //Example: 95 % 26 would result in 17.

			//Increment the code by the character derived from the index.
			$code .= $characters[$index];
		}

		return $code;
	}

	//If there is no session id, therefore no session, start the session.
	if (!session_id()) session_start();

	//Declare the configuration file relative to the current directory.
	$configFile = sprintf("%s/config.php", __DIR__);

	//If the configuration file does not exist.
	if (!file_exists($configFile)) {
		//Require the installer.
		require_once(sprintf("%s/install.php", __DIR__));

		exit;
	}

	//Require the configuration file.
	require_once(__DIR__ . '/config.php');

	//If there is no config variable.
	if (empty($config)) {
		require_once(sprintf("%s/install.php", __DIR__));

		exit;
	}

	$errors = array();
	$warnings = array();

	try {
		/**
		 * Connect to the database. See install.php for explanations.
		 */

		$dsn = "mysql:host={$config['db']['hostname']};port={$config['db']['port']};dbname={$config['db']['database']}";

		$db = new PDO($dsn, $config['db']['username'], $config['db']['password'], array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_EMULATE_PREPARES => false
		));
	//Capture all of the expected exceptions.
	} catch (\PDOException $e) {
		//Show a nice non-specific error to the user.
		$errors[] = "There was an internal server error. Please try again later.";

		//Log the error with more information.
		error_log(sprintf("[%s:%s] %s%s%s", $e -> getFile(), $e -> getLine(), $e -> getMessage(), PHP_EOL, $e -> getTraceAsString()));
	}

	if (!empty($db)) {
		//If the form was submitted (input name="submitted")
		if (isset($_POST['submitted'])) {
			//Get the site name by filtering the $_POST['link'] variable and sanitizing the url.
			$link = filter_input(INPUT_POST, 'link', FILTER_SANITIZE_URL);

			if (empty($link)) {
				$errors[] = 'You must supply a link address to be shortened.';
			}

			//Get the user's IP address by filtering the $_SERVER['REMOTE_ADDR] variable and sanitizing as a url.
			$ip = filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_SANITIZE_URL);

			try {
				//Look for an existing record in the database that has been created by this user recently.
				$statement = $db -> prepare("SELECT id FROM link WHERE created_at >= :date AND ip = :ip");

				//Get the last updated date by subtracting the lastUpdated configuration value, in seconds, from the existing date.
				$lastUpdatedDate = (new DateTime()) -> sub(new DateInterval(sprintf("PT%sS", $config['app']['lastUpdated'])));

				$statement -> bindValue(':date', $lastUpdatedDate -> format('Y-m-d H:i:s')); //Use the SQL DATETIME format.
				$statement -> bindValue(':ip', $ip);

				$statement -> execute();

				//If there is an existing record in the database.
				if ($statement -> rowCount() > 0) {
					$errors[] = "You're trying to shorten too many links too quickly, please slow down.";
				}

				//If there are no errors.
				if (empty($errors)) {
					//Assume that the code exists to start the loop below.
					$alreadyExists = true;

					//Declare the character range.
					$characters = range('a', 'z');

					//Set the default code length.
					$codeLength = 3;

					//While the code already exists in the database.
					while($alreadyExists) {
						//Generate the code.
						$code = generateCode($characters, $codeLength);

						//If the code length is too large.
						if ($codeLength >= 255) {
							//Throw an exception.
							throw new ErrorException('The code length exceeded 255 characters.');
						}

						//Look for an existing record in the database that matches this code.
						$statement = $db -> prepare("SELECT id FROM link WHERE code = :code");

						$statement -> bindValue(':code', $code);

						$statement -> execute();

						//If there is no existing record in the database.
						if ($statement -> rowCount() === 0) {
							//Tell the system that it doesn't exist.
							$alreadyExists = false;

							break;
						}

						//Increment the code length to shortcut having to go through every permutation.
						$codeLength++;
					}

					$statement = $db -> prepare("INSERT INTO link(link, code, ip) VALUES (:link, :code, :ip)");

					$statement -> bindValue(':link', $link);
					$statement -> bindValue(':code', $code);
					$statement -> bindValue(':ip', $ip);

					$statement -> execute();

					//Get the status of https.
					$https = filter_input(INPUT_SERVER, 'HTTPS', FILTER_SANITIZE_URL);

					//Set the scheme.
					$scheme = (!empty($https)) ? 'https://' : 'http://';

					//Replace the scheme just in case there's shorthand protocol or we've upgraded SSL.
					$fullSiteUrl = str_replace(array('http://', 'http://', '//'), $scheme, $config['app']['url']);

					//Set the shortened url and trim the slash off the end of the full site URL.
					$shortenedLink = sprintf("%s/%s", rtrim($fullSiteUrl, '/'), $code);
				}
			//Capture all of the expected exceptions.
			} catch (\ErrorException | \PDOException $e) {
				//Show a nice non-specific error to the user.
				$errors[] = "There was an error generating your shortened link. Please try again.";

				//Log the error with more information.
				error_log(sprintf("[%s:%s] %s%s%s", $e -> getFile(), $e -> getLine(), $e -> getMessage(), PHP_EOL, $e -> getTraceAsString()));
			}
		}

		if (isset($_GET['code']) && !empty($_GET['code'])) {
			//Get the code and sanitize it as a string.
			$code = filter_input(INPUT_GET, 'code', FILTER_SANITIZE_STRING);

			//Look for an existing record in the database that matches this code.
			$statement = $db -> prepare("SELECT * FROM link WHERE code = :code");

			$statement -> bindValue(':code', $code);

			$statement -> execute();

			//If there is no link to redirect to.
			if ($statement -> rowCount() === 0) {
				//Set the status code.
				http_response_code(404);

				//Set not found as true.
				$warnings[] = "Error 404: Link Not Found. Unfortunately, that link may no longer exist. Please try creating a new one!";
			} else {
				//Get the row as an object.
				$row = $statement -> fetchObject();

				//Get the links that this person has hit, or default to an array.
				$hits = $_SESSION['hits'] ?? array();

				//Check if hits is an array and whether this user hasn't recently hit this link.
				if (is_array($hits) && !in_array($row -> id, $hits)) {
					//Update hits analytics.
					$statement = $db -> prepare("UPDATE link SET hits = :hits WHERE id = :id");

					$statement -> bindValue(':id', $row -> id);
					$statement -> bindValue(':hits', ($row -> hits + 1));

					$statement -> execute();

					$hits[] = $row -> id;

					$_SESSION['hits'] = $hits;
				}

				//Redirect to the address with a permanent redirect.
				header(sprintf("Location: %s", $row -> link), TRUE, 301);
			}
		}
	}
?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title><?php print($config['app']['name']); ?></title>
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css" />
		<style type="text/css">
			html, body {
				min-height: 100vh;
			}

			body {
				background-color: #40CECE;
				background: linear-gradient(-125deg, #40CECE 0%, #E84796 50%, #FF8E01 100%);
			}
		</style>
	</head>
	<body>
		<header class="section">
			<div class="container has-text-centered">
				<h1 class="title has-text-white"><?php print($config['app']['name']); ?></h1>
			</div>
		</header>
		<main class="section">
			<section class="container">
<?php if (!empty($shortenedLink)) { ?>
				<div class="field is-grouped is-grouped-centered">
					<div class="control field has-addons is-expanded">
						<div class="control">
							<label class="label button is-static is-medium" for="shortenedLink">
								Your shortened link:
							</label>
						</div>
						<div class="control is-expanded">
							<input id="shortenedLink" class="input is-medium" type="text" value="<?php print($shortenedLink); ?>"
								readonly="readonly" />
						</div>
					</div>
				</div>
<?php } ?>
<?php if (!empty($errors)) { ?>
	<?php foreach($errors as $error) { ?>
				<div class="notification is-danger">
					<?php print($error); ?>
				</div>
	<?php } ?>
<?php } ?>
<?php if (!empty($warnings)) { ?>
	<?php foreach($warnings as $warning) { ?>
				<div class="notification is-warning">
					<?php print($warning); ?>
				</div>
	<?php } ?>
<?php } ?>
				<form action="<?php print($config['app']['url']); ?>" method="POST">
					<div class="field is-grouped is-grouped-centered">
						<div class="control field has-addons is-expanded">
							<div class="control">
								<label class="label button is-static is-medium" for="link">
									Link to shorten:
								</label>
							</div>
							<div class="control is-expanded">
								<input id="link" class="input is-medium" type="text" name="link"
									placeholder="Your address goes here&hellip;" value="<?php print($link ?? null); ?>" />
							</div>
							<div class="control">
								<input type="submit" class="button is-primary is-medium" name="submitted" value="Shorten" />
							</div>
						</div>
					</div>
				</form>
			</section>
		</main>
		<footer class="section">
			<section class="container has-text-centered has-text-white">
				Copyright &copy; <?php print(date('Y')); ?> <?php print($config['app']['name']); ?>. Some Rights Reserved.<br />
				Powered by <a href="https://github.com/mookman288/magelink" target="_blank" rel="noopener">MageLink</a>
			</section>
		</footer>
	</body>
</html>