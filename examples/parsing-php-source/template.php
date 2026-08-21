<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>A PHP template</title>
</head>
<body>
<h1><?php echo $title; ?></h1>

<ul id="menu">
	<li><a href="/">Home</a></li>
	<li><a href="/about">About</a></li>
</ul>

<?php foreach ($posts as $post) : ?>
	<article>
		<h2><?php echo htmlspecialchars($post['title']); ?></h2>
		<p><?php echo htmlspecialchars($post['excerpt']); ?></p>
	</article>
<?php endforeach; ?>
</body>
</html>
