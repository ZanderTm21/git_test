from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.lib import colors
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.platypus import (SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle,
                                ListFlowable, ListItem, PageBreak, HRFlowable)
from reportlab.lib.enums import TA_LEFT

GREEN = colors.HexColor("#0b6b4f")
DARK = colors.HexColor("#0b3d2e")
GREY = colors.HexColor("#52606d")
BOXBG = colors.HexColor("#eef6f2")
BOXBR = colors.HexColor("#b7d8c9")
WARNBG = colors.HexColor("#fdf2e3")
WARNBR = colors.HexColor("#f0c98a")
ADBG = colors.HexColor("#f4f7fb")
ADBR = colors.HexColor("#c3d3e6")
LINE = colors.HexColor("#cbd2d9")

styles = getSampleStyleSheet()
S = {}
S['title'] = ParagraphStyle('title', parent=styles['Title'], fontName='Helvetica-Bold',
                            fontSize=26, textColor=DARK, spaceAfter=2, leading=30, alignment=TA_LEFT)
S['sub'] = ParagraphStyle('sub', fontSize=12, textColor=GREY, leading=16, spaceAfter=10)
S['h2'] = ParagraphStyle('h2', fontName='Helvetica-Bold', fontSize=16, textColor=GREEN,
                         spaceBefore=18, spaceAfter=8, leading=19)
S['h3'] = ParagraphStyle('h3', fontName='Helvetica-Bold', fontSize=12, textColor=DARK,
                         spaceBefore=10, spaceAfter=3, leading=15)
S['body'] = ParagraphStyle('body', fontSize=10.5, textColor=colors.HexColor("#1f2933"),
                           leading=15, spaceAfter=6)
S['small'] = ParagraphStyle('small', fontSize=9, textColor=GREY, leading=12, spaceAfter=4)
S['cell'] = ParagraphStyle('cell', fontSize=9.5, leading=13, textColor=colors.HexColor("#1f2933"))
S['cellb'] = ParagraphStyle('cellb', fontSize=9.5, leading=13, fontName='Helvetica-Bold', textColor=DARK)
S['th'] = ParagraphStyle('th', fontSize=9.5, leading=13, fontName='Helvetica-Bold', textColor=colors.white)
S['boxbody'] = ParagraphStyle('boxbody', fontSize=10, leading=15, textColor=colors.HexColor("#1f2933"))
S['label'] = ParagraphStyle('label', fontSize=9, leading=14, fontName='Helvetica-Bold', textColor=GREEN)

story = []

def P(t, s='body'): story.append(Paragraph(t, S[s]))
def H2(t): story.append(Paragraph(t, S['h2']))
def H3(t): story.append(Paragraph(t, S['h3']))
def sp(h=6): story.append(Spacer(1, h))

def callout(html, bg, br):
    p = Paragraph(html, S['boxbody'])
    t = Table([[p]], colWidths=[170*mm])
    t.setStyle(TableStyle([
        ('BACKGROUND',(0,0),(-1,-1),bg),
        ('BOX',(0,0),(-1,-1),0.8,br),
        ('LEFTPADDING',(0,0),(-1,-1),10),('RIGHTPADDING',(0,0),(-1,-1),10),
        ('TOPPADDING',(0,0),(-1,-1),8),('BOTTOMPADDING',(0,0),(-1,-1),8),
    ]))
    story.append(t); sp(8)

def datatable(header, rows, widths):
    data = [[Paragraph(h, S['th']) for h in header]]
    for r in rows:
        data.append([Paragraph(c, S['cell']) for c in r])
    t = Table(data, colWidths=widths, repeatRows=1)
    ts = [
        ('BACKGROUND',(0,0),(-1,0),GREEN),
        ('GRID',(0,0),(-1,-1),0.5,LINE),
        ('VALIGN',(0,0),(-1,-1),'TOP'),
        ('LEFTPADDING',(0,0),(-1,-1),6),('RIGHTPADDING',(0,0),(-1,-1),6),
        ('TOPPADDING',(0,0),(-1,-1),5),('BOTTOMPADDING',(0,0),(-1,-1),5),
        ('ROWBACKGROUNDS',(0,1),(-1,-1),[colors.white, colors.HexColor("#f6f8f7")]),
    ]
    t.setStyle(TableStyle(ts))
    story.append(t); sp(8)

def bullets(items):
    lst = ListFlowable([ListItem(Paragraph(i, S['body']), leftIndent=10) for i in items],
                       bulletType='bullet', start='•', leftIndent=12)
    story.append(lst); sp(4)

# ---------------- COVER ----------------
P("The Mould Lead-Gen Playbook", 'title')
P("A complete, step-by-step campaign to win mould &amp; damp jobs<br/>Prepared for <b>Lyon Renovations</b>  |  June 2026", 'sub')
callout("<b>What this is:</b> a done-for-you plan to turn mould &amp; damp problems into booked, paying jobs. "
        "You don't sell &quot;mould removal&quot; cold &mdash; you give away a <b>free survey</b>, then close on a fixed-price quote. "
        "Follow the sections in order. Everything you need to copy &amp; paste is inside.", BOXBG, BOXBR)

# ---------------- 1 ----------------
H2("1. The Strategy in One Page")
P("<b>The big idea:</b> Mould is the perfect lead magnet because the urgency is <b>real</b> &mdash; it harms health, "
  "it spreads fast, and for landlords it's now a legal duty. You lead with a free survey (low commitment = more leads), "
  "then convert the survey into a fixed-price job.")
datatable(["The offer", "Why it works"],
          [["Free, no-obligation mould &amp; damp survey + fixed-price quote",
            "People won't book &quot;removal&quot; from an ad, but they'll book a free check. The survey is where you build trust and close."]],
          [60*mm, 110*mm])
H3("Three urgency angles (pick by who you target)")
datatable(["Angle", "Who", "The honest urgency"],
          [["Health", "Families, parents, asthma/elderly", "Spores trigger coughs, asthma &amp; allergies &mdash; worse for kids"],
           ["It spreads &amp; gets costly", "All homeowners", "A small patch becomes a wall/structural job if ignored"],
           ["Legal (Awaab's Law)", "Landlords", "UK landlords must now fix damp &amp; mould fast &mdash; fines &amp; liability"]],
          [40*mm, 50*mm, 80*mm])

# ---------------- 2 ----------------
H2("2. The 7-Day Launch Plan")
datatable(["Day", "Do this"],
          [["Day 1", "Decide your main angle (start with <b>Health</b> for homeowners). Set a budget (start &pound;15&ndash;&pound;20/day)."],
           ["Day 2", "Build the simple landing page (Section 4). One job: get them to book a survey."],
           ["Day 3", "Set up the lead form questions (Section 6) and where leads land (email + phone alert)."],
           ["Day 4", "Create the ads (Section 5). Take 3&ndash;4 real &quot;before&quot; photos of mould jobs you've done."],
           ["Day 5", "Set targeting (Section 7). Launch Facebook/Instagram + one Google Search ad."],
           ["Day 6&ndash;7", "Reply to every lead within the hour (Section 8). Book surveys. Watch the numbers (Section 9)."]],
          [22*mm, 148*mm])

# ---------------- 3 ----------------
H2("3. Tools You'll Need")
datatable(["Job", "Easiest option", "Cost"],
          [["Run the ads", "Meta Ads Manager (Facebook/Instagram) + Google Ads", "Pay per click/lead"],
           ["Landing page", "Carrd, Leadpages, or a page on your existing site", "Free&ndash;&pound;9/mo"],
           ["Capture leads", "Meta &quot;Instant Form&quot; (no website needed) or a form on the page", "Free"],
           ["Get notified fast", "Email + text alert on new lead (Zapier/your CRM)", "Free tier"]],
          [38*mm, 95*mm, 37*mm])
P("Tip: for the fastest start, use a Meta <b>Instant Form</b> ad &mdash; the lead form opens inside Facebook, so you don't even need a website on day one.", 'small')

# ---------------- 4 ----------------
H2("4. The Landing Page (one page, one goal)")
callout("<b>Headline:</b> Got Mould or Damp? Get a Free Survey This Week.<br/>"
        "<b>Sub-headline:</b> We find the cause, treat it properly, and give you a fixed price &mdash; so it doesn't come back.<br/><br/>"
        "<b>3 tick points:</b><br/>"
        "&#10003; Free, no-obligation survey &amp; fixed-price quote<br/>"
        "&#10003; We fix the <i>cause</i>, not just the patch<br/>"
        "&#10003; Local, fast, trusted &mdash; [X] years' experience<br/><br/>"
        "<b>Button:</b> Book My Free Survey<br/>"
        "<b>Trust row:</b> photos of past jobs &bull; reviews/star rating &bull; &quot;fully insured&quot; &bull; areas covered",
        BOXBG, BOXBR)
P("Keep it to one screen of choices: no menu, no distractions, one button repeated top and bottom.", 'small')

# ---------------- 5 ----------------
H2("5. The Ads (copy &amp; paste)")
H3("Facebook / Instagram &mdash; Health angle")
callout("<b>PRIMARY TEXT</b><br/>"
        "That black patch in the corner isn't just ugly &mdash; it's releasing spores into the air your family breathes every day.<br/><br/>"
        "Mould doesn't stay put. What's a small patch today spreads behind walls and skirting within weeks &mdash; turning a quick fix into a costly repair.<br/><br/>"
        "&#10003; Free mould &amp; damp survey<br/>"
        "&#10003; Fixed-price quote, no pressure<br/>"
        "&#10003; Treated <i>and</i> the cause fixed, so it doesn't come back<br/><br/>"
        "A few free survey slots left this week. Book before they're gone.<br/><br/>"
        "<b>HEADLINE:</b> Got Mould? Get a Free Survey This Week<br/>"
        "<b>DESCRIPTION:</b> Fast, local, fixed-price. Stop it before it spreads.<br/>"
        "<b>BUTTON:</b> Book Now", ADBG, ADBR)
H3("Google Search ad &mdash; hottest leads (already searching)")
callout("<b>HEADLINES:</b> Mould Removal Near You &bull; Free Damp &amp; Mould Survey &bull; Fixed-Price &middot; No Mess &middot; Guaranteed<br/>"
        "<b>DESCRIPTION:</b> Black mould spreads fast and harms your health. Book a free survey this week and get a fixed-price quote. Local, trusted, fast response.",
        ADBG, ADBR)
H3("Landlord version &mdash; highest-value leads")
callout("<b>HEADLINE:</b> Landlords: Are You Legally Compliant on Damp &amp; Mould?<br/>"
        "<b>PRIMARY TEXT:</b> New rules mean you must deal with damp and mould in your rental &mdash; fast. Ignore a tenant's "
        "report and you risk fines and liability. We survey, treat, and document it properly so you're covered. Book a free landlord survey this week.",
        ADBG, ADBR)

story.append(PageBreak())

# ---------------- 6 ----------------
H2("6. The Lead Form (keep it short)")
P("Ask only what you need to call them back and price the job. Fewer questions = more leads.")
bullets(["Name", "Phone number", "Postcode / area",
         "Are you a <b>homeowner</b> or <b>landlord</b>?",
         "Where's the mould? (bathroom / bedroom / wall / ceiling / multiple rooms)",
         "How long has it been there? (optional &mdash; signals urgency)",
         "Best time to call you"])

# ---------------- 7 ----------------
H2("7. Targeting &amp; Budget")
datatable(["Setting", "Start with"],
          [["Facebook/Instagram location", "15&ndash;20 mile radius around your base"],
           ["Audience", "Homeowners + landlords, age 30+"],
           ["Google keywords", "&quot;mould removal&quot;, &quot;damp specialist&quot;, &quot;black mould [your town]&quot;, &quot;damp survey near me&quot;"],
           ["Budget", "&pound;15&ndash;&pound;20/day to start; scale up what brings cheap leads"],
           ["Goal", "Cost per lead under your comfort number (e.g. &pound;10&ndash;&pound;25 depending on job value)"]],
          [50*mm, 120*mm])

# ---------------- 8 ----------------
H2("8. Turning Leads Into Jobs (where the money is made)")
callout("<b>The golden rule: call within the hour.</b> Lead value drops fast. A 5-minute callback beats a competitor who calls tomorrow.", WARNBG, WARNBR)
bullets(["<b>Call first, text as backup:</b> &quot;Hi [name], it's [you] from Lyon Renovations about your free mould survey &mdash; when suits for me to pop round?&quot;",
         "<b>Book the survey on that call.</b> Give a date/time then and there.",
         "<b>At the survey:</b> show the cause, explain the fix, hand over a clear fixed-price quote. Offer to start within X days.",
         "<b>Follow up</b> any &quot;thinking about it&quot; leads after 2 days and again after a week."])

# ---------------- 9 ----------------
H2("9. Know What's Working (simple tracking)")
datatable(["Number to watch", "What it tells you"],
          [["Leads per week", "Are the ads working at all?"],
           ["Cost per lead", "Is it affordable? (spend &divide; leads)"],
           ["Surveys booked", "Are leads good quality?"],
           ["Jobs won", "The only number that really matters"]],
          [55*mm, 115*mm])
P("Each week: pause the ad/keyword with the worst cost-per-lead, put that money into the best one. That's it.", 'small')

story.append(PageBreak())

# ---------------- 10 ----------------
H2("10. Video Ad Idea (full concept + script)")
callout("<b>Title:</b> &quot;It's Not Just a Stain&quot;  |  <b>Length:</b> 20&ndash;30 seconds  |  <b>Where:</b> Facebook/Instagram Reels, TikTok, YouTube Shorts<br/>"
        "<b>Style:</b> shot on a phone, real and raw beats polished. Vertical (9:16).", BOXBG, BOXBR)
H3("The hook (first 3 seconds are everything)")
P("Open on an extreme close-up of black mould creeping up a corner, slow zoom. Bold on-screen text: <b>&quot;You think it's just a stain&hellip;&quot;</b>")
H3("Shot-by-shot script")
datatable(["Time", "What we see", "Voiceover / on-screen text"],
          [["0&ndash;3s", "Close-up of black mould in a corner, slow zoom in", "<b>TEXT:</b> &quot;You think it's just a stain...&quot;"],
           ["3&ndash;7s", "Quick cuts: mould behind a bed, around a window, on a ceiling", "<b>VO:</b> &quot;But mould spreads &mdash; and the spores get into the air your family breathes.&quot;"],
           ["7&ndash;12s", "You/your team arriving, clean van, doing a survey with a damp meter", "<b>VO:</b> &quot;We find the real cause, not just the patch.&quot;"],
           ["12&ndash;18s", "Satisfying before &rarr; after: mouldy wall becomes clean, fresh wall", "<b>VO:</b> &quot;Treated properly, so it doesn't come back.&quot;"],
           ["18&ndash;25s", "You smiling to camera, logo, phone number", "<b>VO:</b> &quot;Book your free survey this week.&quot; <b>TEXT:</b> Free Survey &middot; Fixed Price &middot; [Phone]"]],
          [16*mm, 77*mm, 77*mm])
H3("Why this works")
bullets(["<b>Pattern interrupt:</b> the gross close-up + &quot;just a stain&quot; stops the scroll.",
         "<b>Problem &rarr; agitate &rarr; solve:</b> classic structure in under 30 seconds.",
         "<b>The before/after</b> is the most shareable, trust-building moment &mdash; lean into it.",
         "<b>Real you on camera</b> = local trust, which big firms can't fake."])
callout("<b>Make 3 versions of the hook</b> and run them against each other. Same video, different first 3 seconds: "
        "(1) &quot;You think it's just a stain...&quot;  (2) &quot;This costs UK families thousands every year...&quot;  "
        "(3) &quot;Landlords &mdash; this could get you fined.&quot; Keep the winner.", WARNBG, WARNBR)

# ---------------- 11 ----------------
H2("11. Quick-Start Checklist")
callout("&#9744; Pick your main angle (Health to start)<br/>"
        "&#9744; Set budget (&pound;15&ndash;&pound;20/day)<br/>"
        "&#9744; Build the one-page landing page OR a Meta Instant Form<br/>"
        "&#9744; Set up the 7 lead-form questions + instant notifications<br/>"
        "&#9744; Write/paste the ads + grab real before/after photos<br/>"
        "&#9744; Film the 20-second video (3 hook versions)<br/>"
        "&#9744; Set targeting (15&ndash;20 mile radius, 30+, homeowners + landlords)<br/>"
        "&#9744; Launch &mdash; then call every lead within the hour<br/>"
        "&#9744; Each week: cut the worst, scale the best", BOXBG, BOXBR)

sp(10)
story.append(HRFlowable(width="100%", thickness=0.6, color=LINE))
P("Prepared for Lyon Renovations. Built on the marketing skills library (copywriting, ad-creative, paid ads, growth &amp; "
  "conversion frameworks). Keep urgency honest &mdash; the real facts about mould (health, spread, and landlord law) do the selling for you.", 'small')

doc = SimpleDocTemplate("mould-lead-gen-playbook.pdf", pagesize=A4,
                        leftMargin=20*mm, rightMargin=20*mm, topMargin=18*mm, bottomMargin=16*mm,
                        title="The Mould Lead-Gen Playbook", author="Lyon Renovations")
doc.build(story)
print("PDF built OK")
