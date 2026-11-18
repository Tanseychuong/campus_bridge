</main>

<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <p class="footer-brand"><?php logo_mark(); ?> CampusBridge</p>
            <p class="muted small">
                An independent student project (CS341 Web Technologies). CampusBridge is not affiliated with
                or endorsed by any university. All organizations, opportunities and events shown are
                <strong>fictional sample data</strong>.
            </p>
        </div>
        <nav aria-label="Footer: discover">
            <p class="footer-heading">Discover</p>
            <ul>
                <li><a href="<?= e(url('explore.php', ['type' => 'opportunity'])) ?>">Opportunities</a></li>
                <li><a href="<?= e(url('events.php')) ?>">Events</a></li>
                <li><a href="<?= e(url('organizations.php')) ?>">Organizations</a></li>
            </ul>
        </nav>
        <nav aria-label="Footer: about">
            <p class="footer-heading">About</p>
            <ul>
                <li><a href="<?= e(url('about.php')) ?>">About CampusBridge</a></li>
                <li><a href="<?= e(url('about.php')) ?>#recommendations">How recommendations work</a></li>
                <li><a href="<?= e(url('about.php')) ?>#privacy">Privacy &amp; data</a></li>
            </ul>
        </nav>
    </div>
</footer>
</body>
</html>
