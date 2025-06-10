<?php
session_start();
require_once '../includes/dbconfig.inc.php';
require_once '../includes/header.php';
require_once '../includes/nav.php';
?>

<main>
    <h2>About Us</h2>
    <section>
        <h3>The Agency</h3>
        <p>Birzeit Homes, established in 2010, is a leading flat rental agency in Birzeit. We pride ourselves on connecting customers with quality homes. Our awards include the 2023 Best Rental Service in Palestine.</p>
    </section>
    <section>
        <h3>The City</h3>
        <p>Birzeit, located in the Ramallah Governorate, has a population of approximately 5,000. Known for its pleasant weather and historical significance, it is home to Birzeit University. For more information, visit <a href="https://en.wikipedia.org/wiki/Birzeit" target="_blank">Wikipedia</a>.</p>
    </section>
    <section>
        <h3>Main Business Activities</h3>
        <ul>
            <li>Flat rental services</li>
            <li>Property management</li>
            <li>Customer support for tenants and owners</li>
        </ul>
    </section>
</main>

<?php require_once '../includes/footer.php'; ?>