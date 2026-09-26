<?php
/**
 * Default competition terms and privacy notice (leaderboard rules, 24 Sep 2026).
 * These seed Settings → Legal; GMF edits the live wording in the admin after legal review.
 */
declare(strict_types=1);

function default_terms_html(): string
{
    return <<<'HTML'
<p><strong>FLY FOR TWO — Competition terms and conditions.</strong> By entering you agree to these terms. Please also read the <a href="/flyfortwo/privacy">privacy notice</a>, which explains how your personal information is protected under the Protection of Personal Information Act 4 of 2013 (POPIA).</p>

<h2>1. The promoter</h2>
<p>FLY FOR TWO is run by George Musical Festival ("GMF") in partnership with Airports Company South Africa SOC Ltd ("ACSA"), together "the Promoter". Contact: info@georgemusicalfestival.co.za.</p>

<h2>2. Competition period</h2>
<p>Entries open on 24 September 2026 and close on 31 October 2026 at 23:59 (South African time). Entries and link opens received outside this period do not count.</p>

<h2>3. Who can enter</h2>
<ol>
<li>South African residents aged 18 or older with a valid South African cellphone number and a working email address.</li>
<li>Employees, directors and agents of GMF and ACSA, their advertising and promotion agencies, and their immediate families may not enter.</li>
</ol>

<h2>4. How to enter</h2>
<ol>
<li>Entry is free. Complete the entry form on georgemusicalfestival.co.za, through a FLY FOR TWO QR code, on the ACSA website or by any other official channel, then confirm your entry using the link or 6-digit code emailed to you. Only confirmed entries count.</li>
<li>One entry per person, per cellphone number and per email address. Duplicate entries are removed.</li>
<li>After confirming, you receive a personal share link and a dashboard showing your score.</li>
</ol>

<h2>5. How the winners are decided — the leaderboard</h2>
<ol>
<li>Your <strong>score</strong> is the number of different people who open your personal share link during the competition period.</li>
<li>Opens from your own devices do not count. Repeated opens by the same person count once, and no more than 3 opens per internet connection per day are counted for each entrant.</li>
<li>If two or more entrants have the same score, the entrant with more friends who confirmed an entry through their link ranks higher. If still tied, the entrant who reached the score first ranks higher.</li>
<li>A public leaderboard shows the top 10 entrants by first name, surname initial, town and score. The entrant in first place is hidden until the winners are revealed.</li>
<li>The leaderboard is frozen when entries close. The <strong>Grand Prize</strong> goes to the entrant in 1st place and the <strong>Second Prize</strong> to the entrant in 2nd place, subject to verification (clause 8). A snapshot of the final standings is kept as a record of the result.</li>
</ol>

<h2>6. Prizes</h2>
<ol>
<li><strong>Grand Prize:</strong> a weekend getaway for two — return domestic flights, weekend accommodation and shuttle transfers. Travel dates, route and suppliers are confirmed with the winner and are subject to availability within the period set by the Promoter.</li>
<li><strong>Second Prize:</strong> as announced on the competition page.</li>
<li>Prizes are not transferable and cannot be exchanged for cash. The Promoter may replace a prize with one of similar value if it becomes unavailable. Costs not listed as part of a prize (such as spending money, meals or travel insurance) are the winner's responsibility.</li>
</ol>

<h2>7. Fair play</h2>
<ol>
<li>Opens must come from real people choosing to open your link. Automated clicks, bots, click farms, paid clicking services, fake accounts, or misleading or spam messages are not allowed.</li>
<li>The Promoter checks the leaderboard for unusual patterns and may remove invalid opens or disqualify entrants who break these rules. Disqualified entrants are removed from the leaderboard.</li>
</ol>

<h2>8. Verifying and announcing winners</h2>
<ol>
<li>Winners are contacted by phone and email within 3 working days of the reveal and must reply within 5 working days. They may be asked for a copy of their ID to confirm eligibility.</li>
<li>If a winner cannot be reached, does not reply in time, is ineligible or has broken these terms, the prize goes to the next eligible entrant on the leaderboard.</li>
<li>Winners are announced on georgemusicalfestival.co.za by first name, surname initial and town, and with their photo only if they added one and agreed to it being shown.</li>
</ol>

<h2>9. Publicity</h2>
<p>Winners may be invited, but are not obliged, to take part in reasonable publicity, such as a photo or short interview. Any use of a winner's image beyond the winner announcement needs their separate consent.</p>

<h2>10. Your personal information (POPIA)</h2>
<p>Your personal information is processed in line with the Protection of Personal Information Act 4 of 2013, only for the purposes explained in the <a href="/flyfortwo/privacy">privacy notice</a>. You will only receive marketing messages if you ticked the optional box, and you can withdraw that consent at any time.</p>

<h2>11. General</h2>
<ol>
<li>The Promoter runs this competition in line with the Consumer Protection Act 68 of 2008 where it applies.</li>
<li>The Promoter may change these terms, or suspend or end the competition, where required by law or by circumstances outside its control. Changes are published on this page.</li>
<li>To the extent allowed by law, the Promoter is not liable for technical failures, lost or delayed emails, or events outside its reasonable control.</li>
<li>Questions about the competition: info@georgemusicalfestival.co.za.</li>
</ol>
HTML;
}

function default_privacy_html(): string
{
    return <<<'HTML'
<p><strong>This notice explains how FLY FOR TWO collects and protects your personal information under the Protection of Personal Information Act 4 of 2013 (POPIA).</strong></p>

<h2>Who is responsible</h2>
<p>Airports Company South Africa SOC Ltd (ACSA) is the responsible party for the personal information collected through the competition. George Musical Festival (GMF) operates this website and platform on ACSA's behalf, as an operator in terms of POPIA, and does not use competition data for any other purpose. Contact for privacy requests: info@georgemusicalfestival.co.za.</p>

<h2>What we collect</h2>
<ul>
<li><strong>Details you give us:</strong> full name, cellphone number, email address, town, age group, your answer to the travel question, and your consent choices (including your POPIA consent to ACSA).</li>
<li><strong>Your photo,</strong> only if you choose to upload one on your dashboard.</li>
<li><strong>How you entered:</strong> the channel or QR code used, for example which activation point.</li>
<li><strong>Fraud-prevention data:</strong> a scrambled (hashed) version of your internet address, a device identifier and browser type. We use these to stop duplicate entries and fake link opens, not to identify you.</li>
<li><strong>Link opens:</strong> when someone opens your share link we record an anonymous visitor code and a hashed network address, so that each person counts once.</li>
</ul>

<h2>Why we use it</h2>
<ul>
<li>To run the competition: confirm your entry, calculate the leaderboard, and contact and verify winners.</li>
<li>To keep the competition fair and prevent fraud.</li>
<li>To report to ACSA on how the campaign performed, mostly as totals by channel, town, age group and date.</li>
<li>To send you news and travel offers from GMF and ACSA — <strong>only if you ticked the optional marketing box.</strong> You can switch this off on your dashboard or by emailing us.</li>
</ul>
<p>We process your information with your consent and because it is necessary to run the competition you entered.</p>

<h2>What is shown publicly</h2>
<p>On the leaderboard and the live activity ticker we show only your <strong>first name, surname initial and town</strong> (for example "Thandiwe N. from George"). If you win, your first name, surname initial, town and — only if you uploaded one and agreed — your photo may be shown on the website and at the live winner reveal. Your cellphone number and email address are never shown publicly.</p>

<h2>Who we share it with</h2>
<ul>
<li>ACSA, as the responsible party.</li>
<li>Service providers who host the website and deliver our emails, under contracts that require them to keep your information confidential and secure. The email service may process messages on servers outside South Africa. Where this happens we rely on providers bound by data-protection laws or agreements that give protection similar to POPIA (section 72).</li>
<li>Authorities, where the law requires it.</li>
</ul>
<p>We do not sell your personal information.</p>

<h2>How we protect it</h2>
<p>The website uses an encrypted connection (HTTPS). Access to the admin system is limited to authorised GMF and ACSA staff with individual logins, and every administrative action is logged. Network addresses are stored scrambled (hashed). The database is backed up daily.</p>

<h2>How long we keep it</h2>
<p>Competition information is kept for as long as needed to run the competition, award the prizes and keep a record of the result — up to 12 months after the competition closes — and is then deleted or de-identified. If you agreed to marketing, your contact details are kept for that purpose until you unsubscribe.</p>

<h2>Your rights under POPIA</h2>
<ul>
<li>Ask what personal information we hold about you and receive a copy.</li>
<li>Ask us to correct or delete your information.</li>
<li>Object to processing, and withdraw your marketing consent at any time. Withdrawing consent does not affect processing that already happened.</li>
<li>Remove your photo at any time on your dashboard.</li>
</ul>
<p>Email info@georgemusicalfestival.co.za and we will respond within a reasonable time. If you are unhappy with how we handled your information, you may complain to the Information Regulator of South Africa: <a href="https://inforegulator.org.za" target="_blank" rel="noopener">inforegulator.org.za</a>.</p>

<h2>Cookies and storage on your device</h2>
<p>We use a session cookie to keep you signed in, a cookie that stops your own device from counting towards your score, and your browser's storage to remember the entry form if you leave the page to check your email. We do not use advertising cookies.</p>

<h2>Age</h2>
<p>The competition is only open to people aged 18 and older. We do not knowingly collect information about children.</p>
HTML;
}
