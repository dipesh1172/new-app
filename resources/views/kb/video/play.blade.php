<!DOCTYPE html>
<html>
<head>
	<title>{{$video->title}}</title>
  <link href="https://vjs.zencdn.net/6.4.0/video-js.css" rel="stylesheet">
  <style>
  	div > div {
  		width: 100% !important;
  	}
  </style>
</head>

<body style="background-color: #000;">
	@if($video->status != 'Conversion Complete')
		Video is not yet available
		<script>
			setTimeout(function(){
				window.location.reload();
			},3500);
		</script>
	@else
	<center>
		<div style="width:100%;">
		  <video id="my-video" class="video-js" controls preload="auto"
		   data-setup="{ fluid: true, aspectRation: &quot;16:9&quot; }">

		    <source src="/kb/video/{{$video->slug}}" type='video/webm'>
		    <p class="vjs-no-js">
		      To view this video please enable JavaScript, and consider upgrading to a web browser that
		      <a href="https://videojs.com/html5-video-support/" target="_blank">supports HTML5 video</a>
		    </p>
		  </video>
		</div>
	  <br/>
	  <p style="color: #fff; font-size: 20px;">{{nl2br($video->description)}}</p>
	</center>
	@endif
	<script>window.HELP_IMPROVE_VIDEOJS = false;</script>
  <script src="https://vjs.zencdn.net/6.4.0/video.js"></script>
</body>
</html>