<?php
// Chatly Terms of Service. Linked from the signup form ("Learn more"); /terms and /tos route here.
// Bump TERMS_VERSION together with 'terms_version' in api/auth.php (tm_register) when this changes.
const TERMS_VERSION = '2026-11-23';
header('Cache-Control: public, max-age=600');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Chatly Terms of Service</title>
  <meta name="description" content="The terms that apply when you use Chatly (chatly.pk), Pakistan's WhatsApp sales platform for brands and closers.">
  <link rel="canonical" href="https://chatly.pk/terms">
  <meta name="robots" content="index, follow">
  <link rel="icon" type="image/png" href="/assets/images/chatly-icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root { --bg: #f4f8f6; --card: #ffffff; --text: #0f1f18; --muted: #4f6b60; --line: #dde9e3; --accent: #0c9f61; --accent-soft: rgba(12, 159, 97, 0.08); }
    @media (prefers-color-scheme: dark) {
      :root { --bg: #050b09; --card: #0b1613; --text: #e3f1ea; --muted: #93aea3; --line: #1a2c26; --accent: #1fe08a; --accent-soft: rgba(31, 224, 138, 0.08); }
    }
    * { box-sizing: border-box; }
    body { margin: 0; background: var(--bg); color: var(--text); font: 15px/1.7 'Inter', -apple-system, 'Segoe UI', sans-serif; -webkit-font-smoothing: antialiased; }
    a { color: var(--accent); }
    .top { position: sticky; top: 0; z-index: 5; background: color-mix(in srgb, var(--bg) 88%, transparent); backdrop-filter: blur(10px); border-bottom: 1px solid var(--line); }
    .top-in { max-width: 860px; margin: 0 auto; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .brand { font-weight: 800; font-size: 18px; letter-spacing: -.02em; text-decoration: none; color: var(--text); }
    .brand span { color: var(--accent); }
    .back { font-size: 13.5px; font-weight: 700; text-decoration: none; padding: 7px 14px; border-radius: 99px; border: 1px solid var(--line); color: var(--text); background: var(--card); }
    main { max-width: 860px; margin: 0 auto; padding: 28px 16px 64px; }
    h1 { font-size: clamp(28px, 5vw, 38px); line-height: 1.15; letter-spacing: -.03em; margin: 0 0 8px; }
    .meta { color: var(--muted); font-size: 13.5px; margin: 0 0 22px; }
    .summary { background: var(--accent-soft); border: 1px solid color-mix(in srgb, var(--accent) 30%, transparent); border-radius: 14px; padding: 16px 18px; margin-bottom: 26px; }
    .summary b { display: block; margin-bottom: 6px; }
    .summary ul { margin: 0; padding-left: 20px; }
    .toc { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 14px 18px; margin-bottom: 28px; }
    .toc b { font-size: 13px; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); }
    .toc ol { margin: 8px 0 0; padding-left: 22px; columns: 2; column-gap: 28px; font-size: 14px; }
    .toc li { break-inside: avoid; }
    section { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 18px 20px; margin-bottom: 14px; scroll-margin-top: 70px; }
    h2 { font-size: 18px; margin: 0 0 8px; letter-spacing: -.01em; }
    p { margin: 0 0 10px; } p:last-child { margin-bottom: 0; }
    ul.l { margin: 0 0 10px; padding-left: 20px; } ul.l:last-child { margin-bottom: 0; }
    li { margin-bottom: 4px; }
    .caps { font-size: 13.5px; }
    footer { text-align: center; color: var(--muted); font-size: 13px; margin-top: 26px; }
    @media (max-width: 600px) { .toc ol { columns: 1; } section { padding: 16px; } }
  </style>
</head>
<body>
  <header class="top"><div class="top-in"><a class="brand" href="/">Chat<span>ly</span></a><a class="back" href="/login">← Back to sign up</a></div></header>
  <main>
    <h1>Terms of Service</h1>
    <p class="meta">Last updated: 23 November 2026 · Version <?= htmlspecialchars(TERMS_VERSION) ?></p>

    <div class="summary">
      <b>The short version</b>
      <ul>
        <li>By creating an account or using Chatly you agree to these terms.</li>
        <li>When you link your WhatsApp, Chatly sends and receives messages through it for campaigns. WhatsApp may limit or ban any number, and Chatly is not responsible for that.</li>
        <li>Only send messages that are legal, and stop messaging anyone who asks you to.</li>
        <li>Earnings are paid only after a sale is verified. Payout times are estimates.</li>
        <li>Chatly is provided "as is". We do not promise sales, income, or uninterrupted service.</li>
      </ul>
    </div>

    <nav class="toc" aria-label="Contents">
      <b>Contents</b>
      <ol>
        <li><a href="#accept">Accepting these terms</a></li>
        <li><a href="#service">What Chatly is</a></li>
        <li><a href="#accounts">Accounts and eligibility</a></li>
        <li><a href="#whatsapp">Linking your WhatsApp</a></li>
        <li><a href="#use">Acceptable use</a></li>
        <li><a href="#companies">Companies and their campaigns</a></li>
        <li><a href="#numbers">Auto Data numbers</a></li>
        <li><a href="#earnings">Earnings, commissions and withdrawals</a></li>
        <li><a href="#fees">Plans, fees and refunds</a></li>
        <li><a href="#data">Your data and privacy</a></li>
        <li><a href="#ip">Chatly's content and brand</a></li>
        <li><a href="#third">Third-party services</a></li>
        <li><a href="#end">Suspension and termination</a></li>
        <li><a href="#disclaim">Disclaimers</a></li>
        <li><a href="#liability">Limitation of liability</a></li>
        <li><a href="#indemnity">Indemnity</a></li>
        <li><a href="#changes">Changes to these terms</a></li>
        <li><a href="#law">Governing law and disputes</a></li>
        <li><a href="#contact">Contact</a></li>
      </ol>
    </nav>

    <section id="accept">
      <h2>1. Accepting these terms</h2>
      <p>These Terms of Service ("Terms") are an agreement between you and Chatly ("Chatly", "we", "us"), the operator of chatly.pk and its apps. By signing up, ticking the box on the signup form, signing in, or otherwise using Chatly, you agree to these Terms. If you do not agree, do not create an account or use Chatly.</p>
      <p>If you use Chatly for a company, you confirm that you are allowed to accept these Terms for that company, and "you" includes that company.</p>
    </section>

    <section id="service">
      <h2>2. What Chatly is</h2>
      <p>Chatly is a sales platform. Companies use it to run WhatsApp outreach campaigns, manage chats with leads in a shared inbox, and manage their sales team. Team Members (TMs, also called closers) and Team Leaders (TLs) use it to chat with leads, record sales and track their earnings.</p>
      <p>We may add, change or remove features at any time. We may also set limits on use, for example how many messages a number or company may send per day.</p>
    </section>

    <section id="accounts">
      <h2>3. Accounts and eligibility</h2>
      <ul class="l">
        <li>You must be at least 18 years old and able to enter into a binding agreement.</li>
        <li>The information you give us (name, phone number, city, payment details) must be true and kept up to date.</li>
        <li>You are responsible for everything done with your account. Keep your password private and tell us at once if you think someone else has used it.</li>
        <li>One person may not hold several accounts to get more numbers, bonuses or commissions.</li>
        <li>A company may approve, decline, stop or remove its own TMs and TLs at any time.</li>
      </ul>
    </section>

    <section id="whatsapp">
      <h2>4. Linking your WhatsApp</h2>
      <p>To send campaigns, you link your own WhatsApp number to Chatly as a linked device. By linking it, you authorise Chatly to:</p>
      <ul class="l">
        <li>send messages from your number to the people in campaigns you take part in, including campaigns run by your company and campaigns run by Chatly across the platform;</li>
        <li>receive replies to those messages and show them in Chatly to you and to your company's admins;</li>
        <li>show "typing" and similar status in chats you handle in Chatly.</li>
      </ul>
      <p>You can unlink your WhatsApp at any time from Chatly or from WhatsApp → Linked devices. Unlinking stops new sends from your number.</p>
      <p>Chatly is not made, approved or endorsed by WhatsApp or Meta. WhatsApp decides on its own whether to limit, restrict or ban a number, for example when many people block or report it. <strong>You use your number with Chatly at your own risk. Chatly is not responsible for any restriction, ban, loss of chats or loss of a WhatsApp account.</strong> Chatly's protections (daily limits, message gaps, pausing a number WhatsApp starts refusing) lower that risk but cannot remove it.</p>
    </section>

    <section id="use">
      <h2>5. Acceptable use</h2>
      <p>You must not use Chatly to:</p>
      <ul class="l">
        <li>break any law, including Pakistan's Prevention of Electronic Crimes Act 2016 and the laws of any country where your messages are received;</li>
        <li>send messages that are fraudulent, misleading, threatening, harassing, hateful, obscene or that promise guaranteed profits;</li>
        <li>keep messaging anyone who says "stop", asks not to be contacted, or blocks you;</li>
        <li>impersonate any person, company or government body;</li>
        <li>collect, sell or misuse other people's personal data;</li>
        <li>hack, overload, copy, reverse-engineer or get around the limits of Chatly, or access accounts or data that are not yours;</li>
        <li>take leads, customers or commissions that belong to someone else.</li>
      </ul>
      <p>We may remove content, stop campaigns, and suspend accounts that break these rules, without notice.</p>
    </section>

    <section id="companies">
      <h2>6. Companies and their campaigns</h2>
      <p>Each company is responsible for its campaigns: the message text, images and voice notes, the products it sells, the claims it makes, and following the law. Companies must have a lawful reason to contact the people they message, and must honour opt-out requests.</p>
      <p>Chats created by a campaign belong to the company workspace they land in. The company's admins can read the chats of their TMs and TLs.</p>
    </section>

    <section id="numbers">
      <h2>7. Auto Data numbers</h2>
      <p>Chatly may supply phone numbers ("Auto Data") for campaigns. These numbers are provided "as is". We do not promise that a number is on WhatsApp, belongs to any particular person, or is interested in any product. The company running the campaign is responsible for the messages sent to these numbers and for following the law when it contacts them. Auto Data may only be used inside Chatly. You may not copy, export, sell or share it.</p>
    </section>

    <section id="earnings">
      <h2>8. Earnings, commissions and withdrawals</h2>
      <ul class="l">
        <li>Commissions, bonuses and plan rewards are earned only when the sale or activation is verified by the company or Chatly. The amounts shown in Chatly are the terms in force at the time of the sale.</li>
        <li>We or the company may hold back, reverse or cancel earnings that come from fraud, mistakes, fake or duplicate sales, refunds, chargebacks or breaking these Terms.</li>
        <li>A withdrawal can only be requested from your verified available balance, above the minimum shown in Chatly. Payout times (for example 24 to 48 working hours, no payouts on Saturdays and Sundays) are estimates, not guarantees.</li>
        <li>You are responsible for giving correct bank or wallet details. Money sent to wrong details you entered may not be recoverable.</li>
        <li>Your balance is not a bank deposit and earns no interest. You are responsible for any taxes on your earnings.</li>
      </ul>
    </section>

    <section id="fees">
      <h2>9. Plans, fees and refunds</h2>
      <p>Some features need a paid plan (for example a company plan, or a partner plan a TM must hold before using the Team Inbox). Prices are shown before you pay. Unless the plan says otherwise or the law requires it, fees already paid are not refundable. We may change prices for future periods and will show the new price before it applies to you.</p>
    </section>

    <section id="data">
      <h2>10. Your data and privacy</h2>
      <p>To run Chatly we collect and store account details (name, username, phone number, city, email), the messages sent and received through Chatly, campaign and usage records, and payout details. We use this data to provide and protect the service, pay earnings, prevent fraud and abuse, provide support, and meet legal duties.</p>
      <ul class="l">
        <li>Your company's admins can see your chats, messages and results in their workspace.</li>
        <li>Data is kept with our hosting and database providers, who may process it outside Pakistan.</li>
        <li>Our staff may view accounts and chats when needed for support, security, investigating abuse, or following the law.</li>
        <li>We do not sell your personal data. We may share it if the law requires it, to protect people or Chatly, or as part of a sale or merger of our business.</li>
        <li>You can ask us to correct your details, or to close your account and delete your data, unless we must keep it for legal, payment or fraud reasons.</li>
      </ul>
    </section>

    <section id="ip">
      <h2>11. Chatly's content and brand</h2>
      <p>Chatly's software, design, name, logo and mascot belong to Chatly. You may use Chatly only as these Terms allow. You keep ownership of the content you send through Chatly and give us permission to store, process and display it to run the service.</p>
    </section>

    <section id="third">
      <h2>12. Third-party services</h2>
      <p>Chatly works with services run by others, such as WhatsApp, email providers, payment methods and hosting. Their own terms apply to them, and we are not responsible for their availability, decisions or actions.</p>
    </section>

    <section id="end">
      <h2>13. Suspension and termination</h2>
      <p>You may stop using Chatly at any time. We may suspend or close an account, stop a campaign, or limit features at any time if we believe these Terms or the law are being broken, if there is a risk to other users or to Chatly, or if a company stops paying for its plan. Earnings that are verified and not affected by a breach of these Terms remain payable after the account closes.</p>
    </section>

    <section id="disclaim">
      <h2>14. Disclaimers</h2>
      <p class="caps">Chatly is provided "as is" and "as available". To the fullest extent the law allows, we make no promises of any kind, including that Chatly will be uninterrupted, error-free or secure, that messages will be delivered, that any number will stay usable, or that you or your company will make any sales or income. Results shown in Chatly or in our marketing are examples, not promises.</p>
    </section>

    <section id="liability">
      <h2>15. Limitation of liability</h2>
      <p class="caps">To the fullest extent the law allows, Chatly and its owners, staff and partners are not liable for any indirect, incidental, special or consequential loss, or for lost profits, sales, data, goodwill or WhatsApp accounts, even if we were told such loss was possible. Our total liability for any claim about Chatly is limited to the greater of (a) the fees you paid to Chatly in the three months before the claim, or (b) Rs. 10,000.</p>
      <p>Nothing in these Terms limits liability that cannot be limited by law.</p>
    </section>

    <section id="indemnity">
      <h2>16. Indemnity</h2>
      <p>You agree to cover Chatly for any claims, losses, fines and reasonable legal costs that come from your messages and campaigns, your use of Chatly, or your breaking these Terms or the law.</p>
    </section>

    <section id="changes">
      <h2>17. Changes to these terms</h2>
      <p>We may update these Terms. The date at the top shows the latest version. If a change is important we will tell you in Chatly or by email. Using Chatly after a change takes effect means you accept the new Terms.</p>
    </section>

    <section id="law">
      <h2>18. Governing law and disputes</h2>
      <p>These Terms are governed by the laws of Pakistan. Before going to court, please contact us so we can try to solve the problem. Any dispute that cannot be solved this way will be decided by the competent courts of Pakistan. If any part of these Terms cannot be enforced, the rest stays in force. If we do not enforce a right right away, we do not give it up.</p>
    </section>

    <section id="contact">
      <h2>19. Contact</h2>
      <p>Questions about these Terms, your data or your account: <a href="mailto:chatly.pk@gmail.com">chatly.pk@gmail.com</a>.</p>
    </section>

    <footer>© <?= date('Y') ?> Chatly · <a href="/">chatly.pk</a></footer>
  </main>
</body>
</html>
