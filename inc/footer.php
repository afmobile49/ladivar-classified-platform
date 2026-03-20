<?php
$phone = get_setting('admin_phone');
$email = get_setting('admin_email');

// tel لینک تمیز
$tel = preg_replace('/[^0-9+]/', '', (string)$phone);
?>

    </section>

    <?php if (empty($hide_side_ads)): ?>
      <aside class="side side--left">


<?php foreach(side_ads('left') as $ad): ?>

<div class="adbox">

  <?php if (!empty($ad['title_display'])): ?>
    <div class="adtitle">
      <?php echo h($ad['title_display']); ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($ad['image_display'])): ?>
    <img
      src="<?php echo h(BASE_URL . '/uploads/ads/' . $ad['image_display']); ?>"
      alt="<?php echo h($ad['title_display'] ?? ''); ?>"
      style="width:100%;height:auto;border-radius:12px;display:block;margin:8px 0;"
    >
  <?php endif; ?>

  <?php if (!empty($ad['html_display'])): ?>
    <div class="adhtml">
      <?php echo $ad['html_display']; ?>
    </div>
  <?php endif; ?>

</div>

<?php endforeach; ?>

      </aside>
    <?php endif; ?>

</main>

<footer class="footer">
  <div class="footer__inner">

    <div>
      <strong><?php echo is_en() ? 'Contact:' : 'تماس:'; ?></strong>
      <?php if (!empty($tel)): ?>
        <a href="tel:<?php echo h($tel); ?>" dir="ltr" style="unicode-bidi: plaintext; display:inline-block;">
          <?php echo h($phone); ?>
        </a>
      <?php else: ?>
        <span dir="ltr" style="unicode-bidi: plaintext; display:inline-block;">
          <?php echo h($phone); ?>
        </span>
      <?php endif; ?>

      <span class="dot">•</span>

      <strong>Email:</strong>
      <?php if (!empty($email)): ?>
        <a href="mailto:<?php echo h($email); ?>" dir="ltr" style="unicode-bidi: plaintext; display:inline-block;">
          <?php echo h($email); ?>
        </a>
      <?php else: ?>
        <span dir="ltr" style="unicode-bidi: plaintext; display:inline-block;">
          <?php echo h($email); ?>
        </span>
      <?php endif; ?>
    </div>

    <div class="footer__links">
      <a href="<?php echo h(append_lang_to_url(BASE_URL . '/help.php')); ?>"><?php echo h(t('help')); ?></a>
      <a href="<?php echo h(append_lang_to_url(BASE_URL . '/about.php')); ?>"><?php echo h(t('about')); ?></a>
      <a href="<?php echo h(append_lang_to_url(BASE_URL . '/settings.php')); ?>"><?php echo h(t('settings')); ?></a>
    </div>

    <div class="footer__note">
      <?php echo h(get_setting_lang('footer_help')); ?>
    </div>

  </div>
</footer>

<script>
(function(){
  var btn = document.querySelector('.menuBtn');
  var menu = document.querySelector('.topmenu');
  if(!btn || !menu) return;

  btn.addEventListener('click', function(){
    var open = menu.classList.toggle('is-open');
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  menu.addEventListener('click', function(e){
    if(e.target && e.target.tagName === 'A'){
      menu.classList.remove('is-open');
      btn.setAttribute('aria-expanded','false');
    }
  });
})();
</script>

</body>
</html>