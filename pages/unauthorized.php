<?php
require_once '../includes/dbconfig.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';
?>

<main>
    <div class="unauthorized-container">
        <div class="unauthorized-content">
            <h1>غير مصرح</h1>
            <div class="error-icon">⚠️</div>
            <p>عذراً، ليس لديك الصلاحية للوصول إلى هذه الصفحة.</p>
            <p>يرجى العودة إلى <a href="../index.php">الصفحة الرئيسية</a> أو <a href="login.php">تسجيل الدخول</a> بحساب مختلف.</p>
        </div>
    </div>
</main>

<style>
.unauthorized-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 60vh;
    padding: 2rem;
}

.unauthorized-content {
    text-align: center;
    background: var(--white);
    padding: 2rem;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    max-width: 500px;
    width: 100%;
}

.unauthorized-content h1 {
    color: var(--error);
    margin-bottom: 1rem;
}

.error-icon {
    font-size: 4rem;
    margin: 1rem 0;
}

.unauthorized-content p {
    margin: 1rem 0;
    color: var(--text-light);
}

.unauthorized-content a {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: bold;
}

.unauthorized-content a:hover {
    text-decoration: underline;
}
</style>

<?php require_once '../includes/footer.php'; ?> 