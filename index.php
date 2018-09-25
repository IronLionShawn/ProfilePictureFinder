<?php  
	ini_set('display_startup_errors',1);
	ini_set('display_errors',1);
	error_reporting(-1);

	//Used Memcache because it was installed on my server, otherwise would use Memcached for smaller caches, Redis for larger caches
	$memcache = new Memcache();
	$memcache->addServer('localhost',11211);

	/**
	* Simple function to
	* @param  string $email  The potential email address
	* @return boolean        True if the string is an email address, false if not.
	*/
	function validateEmail($email)
	{
		//Generic email regex
		$regex = '/^(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){255,})(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){65,}@)(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22))(?:\.(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22)))*@(?:(?:(?!.*[^.]{64,})(?:(?:(?:xn--)?[a-z0-9]+(?:-[a-z0-9]+)*\.){1,126}){1,}(?:(?:[a-z][a-z0-9]*)|(?:(?:xn--)[a-z0-9]+))(?:-[a-z0-9]+)*)|(?:\[(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){7})|(?:(?!(?:.*[a-f0-9][:\]]){7,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?)))|(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){5}:)|(?:(?!(?:.*[a-f0-9]:){5,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3}:)?)))?(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))(?:\.(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))){3}))\]))$/iD';

		return preg_match($regex, $email);
	}

	/**
	 * Curl GET request
	 * @param  string  $url       The url for the GET request
	 * @param  integer $timeout   Optional timeout time
	 * @return mixed[]            The reponse from the server and the status code
	 */
	function getData($url,$timeout = 5) 
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		$response = curl_exec($ch);
		$status = curl_getinfo($ch,CURLINFO_HTTP_CODE);
		curl_close($ch);

		$return = array('statusCode' => $status, 'body' => $response);

		return $return;
	}

	/**
	* Adds a single variables to APC cache system
	* @param string  $email  The email address used as a key
	* @param mixed   $data   The data being stored in cache
	* @param integer $mins   The number of minutes to store the variable
	*/
	function addToCache($email,$data,$mins)
	{
		global $memcache;
		$ttl = $mins * 60;
		$memcache->add($email,$data,false,$ttl);
	}

	/**
	* Checks to see if a key exists then returns the value if their, null if not
	* @param  string $key   The key to be fetched
	* @return mixed         Returns the data from cache or null if it does not exist
	*/
	function fetchFromCache($key)
	{
		global $memcache;
		$result = $memcache->get($key);
		if ($result) 
		{
		 	return $result;
		} 
		return null;
	}

	$data;
	$apiKey;
	$email;
	$type = 'json';

	if (!empty($_GET['email'])) 
	{
		header("Content-type:application/json");
		$email = $_GET['email'];
		if (validateEmail($email)) 
		{
			$cacheData = fetchFromCache($email);
 			if ($cacheData != null) 
 			{
 				$data = $cacheData;
 			}
 			else
 			{
 				//Had to store file in secure location, environment variables not working on server
 				$apiKey = file_get_contents('./inc/api_key.txt');
 				$url = "https://api.fullcontact.com/v2/person.json?apiKey={$apiKey}&email={$email}";
 				$response = getData($url);
 				
 				if ($response['statusCode'] == 200 || $response['statusCode'] == 404) 
 				{
 					$data = $response['body'];// The directions said just to ouput an image url here, but it is possible to get multiple photo urls back
 					addToCache($email,$data,5);
 				}
 				else
 				{
 					$data = json_encode(array('error' => 'An unexpected error occured'));
 				}
 			}
		}
		else
		{
			$data = json_encode(array('error' => 'Invalid email address'));
		}
	}
	else
	{
		$type = 'form';
	}
?>
<?php if($type == 'form') : ?>
<!doctype html>
<html class="no-js" lang="eng">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="x-ua-compatible" content="ie=edge">
        <title></title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <!-- Place favicon.ico in the root directory -->

        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous">
        <style type="text/css">
        	.inf-card {
				height: 350px;
				width: 300px;
				border-radius: 20px;
				background: rgba(0,0,0,.8);
				margin: auto;
				color: white;
				display: block;
				text-align: center;
			}

			.inf-card h1 {
				margin: 5px 0;
			}

			.inf-card p {
				display: block;
				margin: auto;
			}

			#img-container img {
			    width: 150px;
			    padding: 20px 0;
			}
        </style>
    </head>
    <body>
        <!--[if lte IE 9]>
            <p class="browserupgrade">You are using an <strong>outdated</strong> browser. Please <a href="https://browsehappy.com/">upgrade your browser</a> to improve your experience and security.</p>
        <![endif]-->

        <!-- Add your site or application content here -->
        <section class="inf-card">
			<h1>Profile Photo</h1>
			<form id="email-form">
        		<p>
        			<input type="email" name="email" id="email" placeholder="Email Address" required>
        			<button id="submit-email">Submit</button>
        		</p>
        	</form>
        	<div id="img-container">
        		
        	</div>
		</section>
        
        <script src="https://code.jquery.com/jquery-3.2.1.min.js" integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4=" crossorigin="anonymous"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js" integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy" crossorigin="anonymous"></script>
        <script type="text/javascript">

        	/**
        	 * Validate email, submits to page then adds an image to the image container if available, shows alert if not
        	 * @listens submit  Form submission
        	 */
			$(document).on('submit','#email-form',function(event) {

				event.preventDefault(); // stop form submission
				$('#img-container').empty();
				var email = $('#email')[0].value;

				//secondary validation if not using html5 browser or if validation is somehow bypassed
				if(email == '' || email == undefined || email == null)
				{
					alert('Email address is blank');
					return;
				}

				var url = './index.php?email=' + email;
				$.ajax({
			        url: url,
			        type: 'GET',
			        dataType: 'json',
			        success: function(dataResponse,status,message) {
			            if(dataResponse.status === 200) {
			            	var error = true;//DRY variable
			            	if(dataResponse.hasOwnProperty('photos')) {
			            		if(dataResponse.photos.length > 0) {
					            	$('#img-container').html(`<img src='${dataResponse.photos[0].url}' alt='profile picture' />`);
					            	error = false;
					            }
			            	}

			            	if(error) {
			            		alert('No Images Found');
			            	}
			            }
			            else if(dataResponse.status === 404) {
			            	alert(dataResponse.message);
			            }
			            else {
			            	alert('An unexpected error occured');
			            }
			        },
			        error: function(dataResponse,status,message) {
			        	alert('An unexpected error occured;');
			        },
			        complete: function() {
			        	//
			        }
			    });
			});
        </script>
    </body>
</html>
<?php else : ?>
	 <?php echo $data; ?>
<?php endif; ?>