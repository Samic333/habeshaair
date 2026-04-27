<?php
require_once __DIR__ . '/includes/bootstrap.php';
http_response_code(404);
$page = [
    'title'       => 'Page not found — HabeshAir',
    'description' => 'The page you were looking for could not be found.',
    'canonical'   => url('/'),
    'schema'      => 'organization',
];
include __DIR__ . '/includes/header.php';
?>
<section class="section notfound">
  <div class="container prose">
    <div class="code">404</div>
    <h1>Page not found</h1>
    <p class="lede">The page you were looking for has moved, was renamed, or never existed. From here you can return home or start a charter request.</p>
    <div class="form-actions" style="justify-content:center; margin-top:2rem">
      <a href="/" class="btn btn-outline">Back to home</a>
      <a href="/request.php" class="btn btn-gold">Request a charter</a>
    </div>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
