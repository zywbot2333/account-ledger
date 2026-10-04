    </main>
    <footer class="site-footer"><?= e(APP_NAME) ?> v<?= APP_VERSION ?> · 本地记账工具</footer>
  </div>
</div>
<script src="assets/charts.js"></script>
<?php if (!empty($chart)): ?>
<script>
renderBars(document.getElementById('chart'), <?= json_encode($chart, JSON_UNESCAPED_UNICODE) ?>);
</script>
<?php endif; ?>
<script src="assets/app.js"></script>
</body>
</html>
