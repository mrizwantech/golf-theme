<?php
/**
 * Template Name: Privacy Policy
 */
get_header();

$custom_content = '';
if (have_posts()) {
    while (have_posts()) {
        the_post();
        $custom_content = trim(get_the_content());
    }
}
?>
<main class="privacy-policy-page container">
    <header class="privacy-policy-header">
        <span class="about-eyebrow">Transparency &amp; Trust</span>
        <h1><?php echo esc_html(get_the_title() ?: 'Tee Time Nexus Privacy Policy'); ?></h1>
        <p class="privacy-policy-subtitle">How Tee Time Nexus collects, uses, discloses, and protects personal information across our website, mobile applications, and indoor golf facility.</p>
        <span class="privacy-policy-meta">Effective &amp; Last Updated: October 11, 2026</span>
    </header>

    <div class="privacy-policy-body">
            <h2>1. Who we are and what this policy covers</h2>
            <p>Tee Time Nexus is operated by <strong>Far Nexus LLC</strong>, doing business as Tee Time Nexus (&ldquo;Tee Time Nexus,&rdquo; &ldquo;we,&rdquo; &ldquo;us,&rdquo; or &ldquo;our&rdquo;). This Privacy Policy explains how we collect, use, disclose, and protect personal information when you use <a href="<?php echo esc_url(home_url('/')); ?>">https://teetimenexus.com</a>, our Tee Time Nexus iOS and Android applications, and related account, booking, membership, competition, notification, and facility-access services.</p>
            <p>This policy does not replace our booking terms, cancellation policy, membership terms, or event rules. Third-party services that you visit or use independently may have their own privacy policies.</p>
            <div class="privacy-policy-contact-box">
                <address>
                    <strong>Tee Time Nexus</strong><br>
                    2785 Charlotte Hwy, Suites 11 &amp; 12<br>
                    Mooresville, NC 28117, United States<br><br>
                    <strong>Privacy email:</strong> <a href="mailto:sales@teetimenexus.com">sales@teetimenexus.com</a><br>
                    <strong>Phone:</strong> <a href="tel:+19805033288">+1 (980) 503-3288</a>
                </address>
            </div>

            <h2>2. Information we collect</h2>

            <h3>Account and contact information</h3>
            <p>When you create an account, contact us, or use our services, we collect information you provide, such as your name, email address, telephone number, account identifiers, and communication preferences. We process passwords and authentication information to establish and maintain your account. Our systems also use session and access tokens to recognize an authenticated user.</p>
            <p>If you use Sign in with Apple, we receive the account identifier and information Apple makes available to us, which can include your name and an email address or private relay address. We do not receive your Apple account password. Certain communications or checkout steps may require a separately verified contact email address.</p>

            <h3>Booking, membership, and payment information</h3>
            <p>We collect reservation information, including the selected bay, session date and time, duration, number of players, booking reference, associated customer account, and reservation status. We also maintain membership selections, membership status, related purchases, cancellation or rescheduling requests, and customer-service correspondence.</p>
            <p>Checkout may collect billing and contact information. We maintain order amounts, payment status, transaction references, refunds, and related records needed to administer purchases. Payment details are processed through our checkout and GoDaddy payment services.</p>
            <p>GoDaddy processes payment information needed to complete your transaction under its applicable privacy practices. We receive information needed to confirm payment, administer your order, and handle refunds. Do not send payment-card details to us by email or support message.</p>

            <h3>Signatures and agreement records</h3>
            <p>Our booking process can collect an electronic signature image and a record of when you accepted applicable terms or waiver information. These records may be associated with your account and reservation. Where the booking flow permits it, a signature and acceptance already on file may be reused for a later booking. We use these records to document agreements, administer reservations, and address disputes.</p>

            <h3>League and tournament registration information</h3>
            <p>When you register for a competition, we collect information such as your name, email address, telephone number, event and account identifiers, registration status, entry fee and payment information, and acceptance of event rules or waiver information. You may also provide optional information such as your golf handicap and GOLFZON username.</p>
            <p>We use this information to administer registration, manage capacity and payments, communicate event details, and support event operations. Any additional collection or public display of participant information for a particular event will be described in that event&rsquo;s registration materials or a separate notice.</p>

            <h3>Facility-access information</h3>
            <p>If you enable app-based entry, we process information needed to determine whether you are eligible to enter, such as your customer and reservation identifiers, payment and membership eligibility, permitted access window, and the door involved.</p>
            <p>We use Kisi to provide entry during your authorized booking window. We share your name, email address, and access information needed to enable this service. Kisi may process access attempts and related technical information to operate and secure the door-access service.</p>
            <p>Door access is governed by applicable booking eligibility and access permissions. Changing app permissions or deleting the app does not itself cancel a reservation or erase existing business or access records.</p>

            <h3>Notification information</h3>
            <p>If you enable push notifications, we process a Firebase Cloud Messaging device-registration token and platform information. For signed-in users, the token can be associated with the customer account so we can deliver appropriate notifications to that user&rsquo;s devices. We also process notification preferences, message categories, booking identifiers, delivery outcomes, and technical records used to prevent duplicate messages and troubleshoot delivery.</p>
            <p>Notifications may include booking confirmations, changes, cancellations, reminders, account messages, and optional promotional communications. A notification may be displayed on your device&rsquo;s lock screen depending on your settings.</p>

            <h3>Information from forms and customer support</h3>
            <p>We collect the details you submit through contact, launch-interest, or other forms, including your name, contact details, and message content. We also process communications you exchange with our staff. Please provide only information needed for your request, and do not submit passwords or sensitive documents through ordinary contact forms.</p>

            <h3>Technical and website information</h3>
            <p>Our website, hosting infrastructure, and service providers may process technical information such as IP addresses, request times, browser or device information, pages or endpoints requested, session identifiers, and errors. For example, the application code uses IP-related information to limit repeated authentication or form requests. Technical information helps operate the services, prevent misuse, and diagnose problems.</p>
            <p>See Section 5 for information about cookies and similar technologies.</p>

            <h2>3. App permissions and information on your device</h2>
            <p><strong>Bluetooth and nearby devices.</strong> These permissions support communication with Kisi readers and proximity-based door access.</p>
            <p><strong>Location permission for reader proximity.</strong> iOS places certain Bluetooth beacon functions under its location-permission system, and Android may require location or nearby-device permissions for scanning. Our door-access feature uses these permissions to detect a nearby reader rather than to transmit GPS coordinates to Tee Time Nexus. Reader-proximity and access events are distinct from precise GPS location. You can disable these permissions in device settings, but proximity-based unlocking may stop working.</p>
            <p><strong>Notifications.</strong> The app requests permission before showing push notifications. You can change notification permission in your device settings and use available app settings to manage supported categories.</p>
            <p><strong>Calendar.</strong> If you choose to add a reservation to your calendar, booking details such as the date, time, venue, and notes are passed to the calendar application or service you select. The calendar feature is intended to add a reservation, not to collect the contents of your existing calendar. Your calendar provider handles the saved event under its own privacy practices.</p>
            <p><strong>Local storage.</strong> The app stores authentication tokens and certain preferences on the device and may cache public content to improve performance. Signing out, clearing app data, or uninstalling can remove some local information, but does not necessarily remove server-side records. Device secure-storage behavior can vary by platform.</p>

            <h2>4. How we use information</h2>
            <p>We use personal information to:</p>
            <ul>
                <li>Create, authenticate, and administer customer accounts.</li>
                <li>Provide booking, membership, checkout, cancellation, rescheduling, and support services.</li>
                <li>Process purchases, manage refunds, maintain transaction records, and investigate payment disputes.</li>
                <li>Register participants and administer leagues or tournaments.</li>
                <li>Record signatures, terms acceptance, and event acknowledgments.</li>
                <li>Grant, limit, revoke, and troubleshoot eligible facility access.</li>
                <li>Deliver service communications and permitted promotional messages.</li>
                <li>Maintain notification preferences and associate devices with the appropriate account.</li>
                <li>Operate and improve our services, diagnose errors, and prevent fraud or unauthorized access.</li>
                <li>Meet applicable legal obligations, establish or defend legal claims, and protect customers, staff, and property.</li>
            </ul>
            <p>Where applicable law requires consent for a particular activity, we seek that consent. Refusing optional information or permissions may limit the associated feature but does not necessarily prevent use of unrelated features.</p>

            <h2>5. Cookies and similar technologies</h2>
            <p>Our website uses cookies and comparable session technologies for functions such as login, authentication, checkout, shopping-cart continuity, and security. Embedded checkout pages opened inside the app may also use these technologies.</p>
            <p>You can generally block or delete cookies using your browser settings. Doing so may prevent login, checkout, or other website features from working correctly.</p>
            <p>If you follow a link to another service or interact with third-party content, that provider may receive your IP address and other technical information under its own privacy practices.</p>

            <h2>6. When we disclose information</h2>
            <p>We disclose information as needed for the following purposes:</p>
            <p><strong>Service providers.</strong> Providers support hosting, databases, checkout and payments, messaging, security, technical support, and facility access. Relevant providers include Google/Firebase for cloud notification and backend services, Kisi for door-access services, Apple for Apple sign-in and iOS notification delivery, GoDaddy for payment processing, and our hosting and communication providers. WordPress and WooCommerce are the software supporting our website and checkout; use of that software does not by itself mean its developers receive all customer information.</p>
            <p><strong>Authorized staff and event personnel.</strong> Employees, contractors, and event administrators receive information needed to handle bookings, memberships, registrations, access, and support.</p>
            <p><strong>At your direction.</strong> We may disclose information when you request it, share a booking, export a calendar event, or otherwise choose to communicate through another service.</p>
            <p><strong>Legal and protective purposes.</strong> We may disclose information to comply with applicable law or a valid legal process, investigate fraud, enforce agreements, or protect the rights and safety of customers, staff, or others.</p>
            <p><strong>Business changes.</strong> Information may be disclosed in connection with a merger, acquisition, financing, reorganization, or sale of business assets, subject to applicable protections and legal requirements.</p>
            <p><strong>We do not sell or rent your personal information to third parties.</strong> Disclosures to providers such as GoDaddy, Google/Firebase, and Kisi are made to operate the services described in this policy, rather than to sell customer information.</p>

            <h2>7. Email, SMS, and promotional choices</h2>
            <p>We may send operational messages concerning your account, purchases, bookings, access, or events. These messages are separate from optional marketing communications.</p>
            <p>Where offered, promotional email and SMS choices are collected separately. You can unsubscribe from promotional email using its unsubscribe link or contact us to change your preference. If promotional SMS is offered, its enrollment notice identifies the applicable message terms and opt-out method. Opting out of marketing does not cancel your account or bookings.</p>
            <p>Push notification preferences can be managed through available app controls and device settings. Disabling notifications may mean that you miss a booking reminder, but it does not by itself change your reservation or access window.</p>

            <h2>8. Retention</h2>
            <p>We do not automatically delete accounts after a fixed period of inactivity. We retain personal information for as long as reasonably necessary to provide our services, manage accounts and bookings, maintain required business records, resolve disputes, and comply with legal obligations. Retention depends on the type of information and its purpose.</p>
            <p>You may request deletion of your account and associated personal information by calling <a href="tel:+19805033288">+1 (980) 503-3288</a> or emailing <a href="mailto:sales@teetimenexus.com">sales@teetimenexus.com</a>. Certain records may be retained where necessary for legal, accounting, security, or dispute-resolution purposes.</p>
            <p>Expiration of a booking or access permission does not necessarily erase related transaction or security records. Backup copies may remain until the applicable backup cycle expires. Information retained for a legal obligation or dispute is used for the relevant purpose rather than automatically retained for marketing.</p>

            <h2>9. Privacy requests and account deletion</h2>
            <p>Call <strong><a href="tel:+19805033288">+1 (980) 503-3288</a></strong> or email <strong><a href="mailto:sales@teetimenexus.com">sales@teetimenexus.com</a></strong> to ask about your information, request a correction or copy, change communication preferences, or request deletion of your account and associated personal information. For email requests, use the subject &ldquo;Privacy Request&rdquo; or &ldquo;Account Deletion Request.&rdquo; For telephone or email requests, identify the email address associated with your account. Do not email your password or payment-card information.</p>
            <p>We may verify your identity using proportionate measures before acting on a request. Depending on applicable law and the request, we may retain information needed for outstanding transactions, accounting obligations, fraud prevention, security, or legal claims. We will explain applicable limitations when responding.</p>
            <p>Deleting the app does not delete your account. A deletion request does not by itself determine whether you are entitled to a refund or cancel a contractual payment obligation; those matters are handled under the applicable booking or membership terms.</p>
            <p>Depending on your residence and applicable law, you may have additional rights concerning access, correction, deletion, portability, consent withdrawal, or opting out of particular uses. Contact us to exercise applicable rights. We will handle requests within the time required by applicable law and will not unlawfully discriminate against you for exercising your rights.</p>

            <h2>10. Security</h2>
            <p>We use administrative and technical safeguards intended to reduce the risk of unauthorized access, loss, or disclosure. Safeguards include authenticated requests, protected storage for mobile authentication tokens, restrictions on backend data access, and signed server-to-server notification requests. The effectiveness of safeguards also depends on deployment, access management, maintenance, and configuration.</p>
            <p>No method of transmission or storage is completely secure. Keep your account credentials confidential, protect your device, and notify us if you suspect unauthorized access. Where applicable, we respond to security incidents and provide legally required notices.</p>

            <h2>11. Children and family participation</h2>
            <p>Where minors participate in facility activities or junior events, their parent or guardian should handle registrations and provide only information needed for participation. Parents and guardians can contact us with questions about information provided in connection with a minor&rsquo;s participation.</p>
            <p>If you believe a child has provided information in circumstances requiring parental consent that was not obtained, contact us so we can investigate and take appropriate action. Any program intentionally collecting information directly from children will require appropriate notices and consent procedures under applicable law.</p>

            <h2>12. International processing</h2>
            <p>We operate in the United States. Our service providers may process or store information in the United States or other countries where they operate. Privacy laws in those countries may differ from those in your country. Where applicable law requires safeguards for international transfers, the relevant safeguards must be implemented for those transfers.</p>

            <h2>13. Third-party services and facility systems</h2>
            <p>Our services may link to payment services, maps, social networks, calendar providers, and other third-party resources. Their independent collection and use of information is governed by their own notices.</p>
            <p>GOLFZON simulator services may separately process gameplay, swing, score, or simulator-account information. This policy&rsquo;s reference to an optional GOLFZON username does not mean that our website or mobile application collects every category of simulator data. Consult the applicable simulator-provider notices for information processed through its services.</p>

            <h2>14. Changes to this policy</h2>
            <p>We may update this policy to reflect changes in our services, information practices, or legal requirements. We will post the updated policy and revise the date above. Where required, we will provide additional notice or obtain consent for material changes.</p>

            <h2>15. Contact us</h2>
            <p>For privacy questions, requests, or concerns:</p>
            <div class="privacy-policy-contact-box">
                <address>
                    <strong>Tee Time Nexus / Far Nexus LLC</strong><br>
                    2785 Charlotte Hwy, Suites 11 &amp; 12<br>
                    Mooresville, NC 28117, United States<br><br>
                    <strong>Email:</strong> <a href="mailto:sales@teetimenexus.com">sales@teetimenexus.com</a><br>
                    <strong>Phone:</strong> <a href="tel:+19805033288">+1 (980) 503-3288</a>
                </address>
            </div>
    </div>
</main>
<?php get_footer(); ?>
