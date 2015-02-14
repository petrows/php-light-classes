<!DOCTYPE html>
<html>
<head>
	<title>Template example</title>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<link rel="stylesheet" type="text/css" href="{{$_url}}/tpl/style.css" />
</head>
<body>

<div id="all">
	<div id="menu">
		  <a href="?p=index">Index</a> 
		| <a href="?p=form">Form</a>
		| <a href="?p=table">Table</a>
		| <a href="?p=genusers">Generate Users</a>
	</div>
	{{if $errors}}
		<div id="errors">
			<p>Errors:</p>
			<ul>
				{{$errors}}
			</ul>
		</div>
	{{/if}}
	<div id="main">
		
	
		{{$main}}
	</div>

	{{if $debug}}
		<div id="debug">
			<p class="title">DB Debug:</p>
			{{$db_debug}}
		</div>
	{{/if}}
</div>

</body>
</html>
