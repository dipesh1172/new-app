<!doctype html>
<html>
	<head>
		<meta charset="utf-8">
	    <meta http-equiv="X-UA-Compatible" content="IE=edge">
	    <meta name="viewport" content="width=device-width, initial-scale=1">
		<title>Redbook</title>
		<script src="{{asset('js/polyfills.js')}}" type="text/javascript"></script>
		<script src="{{route('redbook.data')}}" type="text/javascript"></script>
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">

		<link href="{{asset('css/redbook-style.css')}}" rel="stylesheet" type="text/css" />
		<link rel="manifest" href="{{route('redbook.manifest')}}" />
		<link rel="shortcut icon" href="{{asset('img/redbook-logo.png')}}" />
	</head>
	<body>
	<nav class="navbar navbar-deglyphiconult navbar-fixed-top">
	  <div class="container-fluid">
	    <!-- Brand and toggle get grouped for better mobile display -->
	    <div class="navbar-header">
	        <a class="navbar-brand" href="#"><img src="/img/tpv-logo.png" />Redbook</a>
	    </div>


	      <form id="search-form" class="navbar-form navbar-center">
	        <div class="form-group">
	          <input id="search" type="search" placeholder="Type to Search" class="form-control" />

	        	<button title="Clear" type="button" id="clear" class="navbar-btn btn btn-info"><span class="glyphicon glyphicon-remove"></span></button>
	        	<button title="Show Results" type="button" id="show-results" class="navbar-btn btn btn-success"><span class="glyphicon glyphicon-list"></span></button>
	        	<button title="Transition Statements" id="transitionsButton" type="button" class="btn btn-warning" data-toggle="modal" data-target="#transitionsModal"><span class="glyphicon glyphicon-road"></span></button>
	        </div>
	      </form>


	  </div><!-- /.container-fluid -->
	</nav>
	<div id="wrap">

		<div id="wrapper">
			<div class="first-row">
				<div id="results"></div>
			</div>
			<div class="second-row">
				<iframe id="redbook" src=""></iframe>
			</div>
		</div>
		</div>
		<div class="modal glyphiconde" id="transitionsModal" role="dialog">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
	        			<button type="button" class="close" data-dismiss="modal">&times;</button>
	       				 <h4 class="modal-title">Transition Statements</h4>
	       				 <button title="3rd Party Exit Statement" type="button" class="btn btn-primary" data-loc="t.11"><span class="glyphicon glyphicon-gift"></span></button>
	       				 <button title="Confused" type="button" class="btn btn-primary" data-loc="t.13"><span class="glyphicon glyphicon-question-sign"></span></button>
	       				 <button title="In a Hurry" type="button" class="btn btn-primary" data-loc="t.2"><span class="glyphicon glyphicon-fire"></span></button>
	       				 <button title="Tech Issues" type="button" class="btn btn-primary" data-loc="t.6"><span class="glyphicon glyphicon-alert"></span></button>
	       				 <button title="Clear Yes/No" type="button" class="btn btn-primary" data-loc="t.9"><span class="glyphicon glyphicon-check"></span></button>
	       				 <button title="Supervisor Request" type="button" class="btn btn-primary" data-loc="t.10"><span class="glyphicon glyphicon-user"></span><span class="glyphicon glyphicon-bullhorn"></span></button>
	       				 <button title="Hold Time Elapsed" type="button" class="btn btn-primary" data-loc="t.12"><span class="glyphicon glyphicon-earphone"></span><span class="glyphicon glyphicon-time"></span></button>
	       				 <button title="2 Voices" type="button" class="btn btn-primary" data-loc="t.14"><span class="glyphicon glyphicon-user"></span><span class="glyphicon glyphicon-user"></span></button>
	       				 <button title="Language Barrier" type="button" class="btn btn-primary" data-loc="t.15"><span class="glyphicon glyphicon-user"></span><span class="glyphicon glyphicon-option-vertical"></span><span class="glyphicon glyphicon-user"></span></button>
	     			</div>
					<div class="modal-body">
						<iframe id="tstmt" src="https://docs.google.com/document/d/13Ig4AdcmUG0i9DmHwxftWeUY86Zv7nOEJoX1lDNMMPQ/pub"></iframe>
					</div>
				</div>
			</div>
		</div>
		<div class="footer">
			<div class="container">
				<p>Copyright &copy; <?php echo (date('Y')); ?> TPV.com | Version <?php echo ($version); ?> <a id="feedback-link" class="btn btn-warning" href="https://docs.google.com/forms/d/e/1FAIpQLSfHXofPgDBsvrVHsxRTUGmTogDVHO8PhXcLqG7Zx7Q3b5BAdg/viewform?usp=sf_link">Feedback</a></p>
            </div>
        </div>
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
        <script src="{{asset('js/redbook-main.js')}}" type="text/javascript"></script>
    </body>
</html>