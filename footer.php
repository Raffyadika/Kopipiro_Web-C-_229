<?php
// footer.php — penutup halaman. Isi $extraScripts (HTML <script>) sebelum include bila perlu.
?>
    </main>
    <footer class="text-center text-muted small py-4">☕ Kopipiro · Dibuat dengan PHP &amp; MySQL</footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?= $extraScripts ?? '' ?>
</body>

</html>
