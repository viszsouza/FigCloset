<?php
$__settings = get_settings();
?>
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <div class="logo" style="margin-bottom:14px;">VISZ <span>Closet</span></div>
                <p style="color:rgba(244,239,230,0.65); font-size:0.9rem;">Aluguel de roupas de marca com curadoria premium e recomendação de tamanho por medidas reais.</p>
            </div>
            <div>
                <h5>Navegue</h5>
                <a href="<?= BASE_URL ?>/roupas.php">Catálogo</a>
                <a href="<?= BASE_URL ?>/minhas-medidas.php">Minhas medidas</a>
                <a href="<?= BASE_URL ?>/como-funciona.php">Como funciona</a>
                <a href="<?= BASE_URL ?>/sobre.php">Sobre nós</a>
            </div>
            <div>
                <h5>Sua conta</h5>
                <a href="<?= BASE_URL ?>/login.php">Entrar</a>
                <a href="<?= BASE_URL ?>/cadastro.php">Criar conta</a>
                <a href="<?= BASE_URL ?>/pedido-status.php">Acompanhar pedido</a>
            </div>
            <div>
                <h5>Fale conosco</h5>
                <a href="<?= e($__settings['instagram_url'] ?? '#') ?>" target="_blank" rel="noopener">Instagram <?= e($__settings['instagram_handle'] ?? '') ?></a>
                <a href="https://wa.me/<?= e($__settings['whatsapp_number'] ?? '') ?>" target="_blank" rel="noopener">WhatsApp</a>
            </div>
        </div>
        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> VISZ Closet. Todos os direitos reservados.</span>
            <span>Feito para quem ama moda sem precisar comprar tudo.</span>
        </div>
    </div>
</footer>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<?php if (!empty($extraScripts)) echo $extraScripts; ?>
</body>
</html>
