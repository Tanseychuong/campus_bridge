-- =====================================================================
-- CampusBridge sample data. EVERYTHING HERE IS FICTIONAL.
--
-- Dates are written relative to the day you import this file, e.g.
--   CURDATE() + INTERVAL 12 DAY + INTERVAL '23:59' HOUR_MINUTE
-- means "12 days from today at 23:59". Re-import to refresh the dates.
--
-- Demo accounts (password for all of them: CampusBridge2026!)
--   student@example.org  (student, Hillside Campus)
--   riverside@example.org (student, Riverside Campus)
--   rep2@example.org … rep16@example.org (rep for organization 2 … 16)
--   rep@example.org      (organization rep for Code & Circuits Society)
--   admin@example.org    (administrator)
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ---------------------------------------------------------------- campuses
INSERT INTO campuses (id, name, short_name) VALUES
    (1, 'Hillside Campus', 'Hillside'),
    (2, 'Riverside Campus', 'Riverside');

INSERT INTO interests (slug, label) VALUES
    ('computer-science', 'Computer Science'),
    ('engineering', 'Engineering'),
    ('entrepreneurship', 'Entrepreneurship'),
    ('research', 'Research'),
    ('business', 'Business'),
    ('social-impact', 'Social Impact'),
    ('sustainability', 'Sustainability'),
    ('arts', 'Arts & Culture'),
    ('leadership', 'Leadership'),
    ('health', 'Health & Wellbeing');

-- ---------------------------------------------------------------- organizations
INSERT INTO organizations (id, name, short_name, category, summary, description, activities, contact_email, meeting_info, membership, founded_year, member_count, color) VALUES (1, 'Code & Circuits Society', 'C&C', 'tech', 'Weekly build nights, coding workshops and peer mentoring for anyone curious about software.', 'Code & Circuits is a student-run community for people who like to build things with software and hardware. We host weekly build nights, beginner-friendly workshops on web and mobile development, and a peer mentoring scheme that pairs first-years with experienced builders. Members also form teams for hackathons and coding competitions throughout the year.', 'Weekly build nights
Beginner web development workshops
Peer mentoring
Hackathon team formation', 'codecircuits@example.org', 'Thursdays, 18:30 – 20:30, Engineering Lab 2', 'Open to all students. Attend any build night and add your name to the members list — no prior experience required.', 2016, 140, '#2457C5');
INSERT INTO organization_interests VALUES (1, 'computer-science');
INSERT INTO organization_interests VALUES (1, 'engineering');
INSERT INTO organizations (id, name, short_name, category, summary, description, activities, contact_email, meeting_info, membership, founded_year, member_count, color) VALUES (2, 'Venture Lab Collective', 'VLC', 'entrepreneurship', 'A community for student founders testing ideas, pitching and learning from operators.', 'Venture Lab Collective helps students move from idea to tested venture. We run customer-discovery sprints, pitch clinics and fireside chats with founders. Members get feedback on business models and support preparing for startup competitions.', 'Customer discovery sprints
Pitch clinics
Founder fireside chats
Startup competition prep', 'venturelab@example.org', 'Tuesdays, 17:00, Innovation Hub', 'Apply with a short form at the start of each semester. Students without an idea yet are welcome.', 2018, 85, '#A2560F');
INSERT INTO organization_interests VALUES (2, 'entrepreneurship');
INSERT INTO organization_interests VALUES (2, 'business');
INSERT INTO organizations (id, name, short_name, category, summary, description, activities, contact_email, meeting_info, membership, founded_year, member_count, color) VALUES (3, 'Green Campus Initiative', 'GCI', 'service', 'Student projects that reduce waste, save energy and make campus life more sustainable.', 'Green Campus Initiative runs practical sustainability projects: recycling drives, energy audits, tree planting and awareness campaigns. Members learn project management and data collection while making measurable improvements to campus life.', 'Recycling drives
Energy audits
Tree planting
Sustainability awareness week', 'greencampus@example.org', 'Every other Wednesday, 16:00, Student Centre Room 3', 'Join any project team by signing up at a general meeting.', 2019, 60, '#22704A');
INSERT INTO organization_interests VALUES (3, 'sustainability');
INSERT INTO organization_interests VALUES (3, 'social-impact');
INSERT INTO organizations (id, name, short_name, category, summary, description, activities, contact_email, meeting_info, membership, founded_year, member_count, color) VALUES (4, 'Undergraduate Research Circle', 'URC', 'academic', 'Connects students with research methods training, faculty projects and paper-reading groups.', 'The Undergraduate Research Circle helps students get started in research. We run methods workshops, a monthly paper-reading group and an annual student research showcase. We also share calls for research assistants and help members prepare applications.', 'Research methods workshops
Paper-reading group
Research showcase
RA application support', 'researchcircle@example.org', 'First Monday of the month, 18:00, Library Seminar Room', 'Open to all. Sign up for the mailing list at any event.', 2017, 55, '#6B4FBB');
INSERT INTO organization_interests VALUES (4, 'research');
INSERT INTO organization_interests VALUES (4, 'computer-science');
INSERT INTO organization_interests VALUES (4, 'health');
INSERT INTO organizations (id, name, short_name, category, summary, description, activities, contact_email, meeting_info, membership, founded_year, member_count, color) VALUES (5, 'Hillside Debate Union', 'HDU', 'academic', 'Competitive debate, public speaking practice and inter-university tournaments.', 'Hillside Debate Union trains students in argumentation, research and public speaking. We practise British Parliamentary format weekly and send teams to inter-university tournaments. New speakers receive structured coaching from experienced members.', 'Weekly practice debates
Speaker coaching
Inter-university tournaments
Public forums', 'debate@example.org', 'Mondays & Fridays, 19:00, Lecture Hall B', 'Attend two practice sessions to become a full member.', 2012, 48, '#B23A48');
INSERT INTO organization_interests VALUES (5, 'leadership');
INSERT INTO organization_interests VALUES (5, 'social-impact');
INSERT INTO organizations (id, name, short_name, category, summary, description, activities, contact_email, meeting_info, membership, founded_year, member_count, color) VALUES (6, 'Women in STEM Network', 'WiSTEM', 'professional', 'Mentorship, skills sessions and community for women studying science and technology.', 'Women in STEM Network builds a supportive community for women in science, technology, engineering and mathematics. We host mentorship circles with alumni, technical skill sessions and outreach to secondary schools. All students who support our mission are welcome.', 'Alumni mentorship circles
Technical skills sessions
Secondary school outreach
Career panels', 'wistem@example.org', 'Wednesdays, 18:00, Engineering Lab 1', 'Complete the short interest form; mentorship circles open each semester.', 2015, 92, '#C2477E');
INSERT INTO organization_interests VALUES (6, 'computer-science');
INSERT INTO organization_interests VALUES (6, 'engineering');
INSERT INTO organization_interests VALUES (6, 'leadership');
INSERT INTO organizations (id, name, short_name, category, summary, description, activities, contact_email, meeting_info, membership, founded_year, member_count, color) VALUES (7, 'Finance & Investment Club', 'FIC', 'professional', 'Learn markets, personal finance and analysis through case studies and a simulated portfolio.', 'The Finance & Investment Club teaches practical finance through weekly case studies, a simulated investment portfolio and sessions on personal finance. Members prepare for finance careers and case competitions.', 'Simulated portfolio
Case study nights
Personal finance workshops
Case competition teams', 'finance@example.org', 'Thursdays, 17:30, Business Wing Room 4', 'Open sign-up at the start of each semester.', 2014, 70, '#1F7A8C');
INSERT INTO organization_interests VALUES (7, 'business');
INSERT INTO organization_interests VALUES (7, 'entrepreneurship');
INSERT INTO organizations (id, name, short_name, category, summary, description, activities, contact_email, meeting_info, membership, founded_year, member_count, color) VALUES (8, 'Community Outreach Corps', 'COC', 'service', 'Volunteering with nearby schools and communities: tutoring, health drives and literacy.', 'Community Outreach Corps organises regular volunteering with nearby communities. Our programmes include after-school tutoring, reading clubs, health screening support and community clean-ups. Volunteers receive orientation before their first placement.', 'After-school tutoring
Reading clubs
Health drive support
Community clean-ups', 'outreach@example.org', 'Saturdays, 09:00, Main Gate (transport provided)', 'Attend an orientation session, then choose a programme.', 2011, 110, '#8A5D0C');
INSERT INTO organization_interests VALUES (8, 'social-impact');
INSERT INTO organization_interests VALUES (8, 'health');
INSERT INTO organization_interests VALUES (8, 'leadership');
INSERT INTO organizations (id, name, short_name, category, summary, description, activities, contact_email, meeting_info, membership, founded_year, member_count, color) VALUES (9, 'Open Design Studio', 'ODS', 'arts', 'Product design, illustration and UX critique sessions for designers and developers.', 'Open Design Studio is a space for visual and product design. We run UX critique sessions, illustration jams and collaborative projects with student organisations that need branding or interface design help.', 'UX critique sessions
Illustration jams
Design-for-good projects
Portfolio reviews', 'designstudio@example.org', 'Fridays, 16:00, Studio Room', 'Drop in to any session. Bring a sketchbook or laptop.', 2020, 38, '#8E5BD9');
INSERT INTO organization_interests VALUES (9, 'arts');
INSERT INTO organization_interests VALUES (9, 'computer-science');
INSERT INTO organizations (id, name, short_name, category, summary, description, activities, contact_email, meeting_info, membership, founded_year, member_count, color) VALUES (10, 'Robotics & Automation Team', 'RAT', 'tech', 'Design, build and program robots for competitions and real-world automation problems.', 'The Robotics & Automation Team designs and builds robots for competitions and campus automation projects. Members learn mechanical design, embedded programming and control systems in small project teams.', 'Competition robot builds
Embedded programming
CAD & 3D printing
Automation projects', 'robotics@example.org', 'Saturdays, 13:00, Fabrication Lab', 'Recruitment opens at the start of each semester with a short hands-on challenge.', 2017, 34, '#3D5A80');
INSERT INTO organization_interests VALUES (10, 'engineering');
INSERT INTO organization_interests VALUES (10, 'computer-science');
INSERT INTO organizations (id, name, short_name, category, summary, description, activities, contact_email, meeting_info, membership, founded_year, member_count, color) VALUES (11, 'Mind & Body Wellness Collective', 'MBW', 'sports', 'Fitness sessions, mental health awareness and peer support for a balanced student life.', 'The Mind & Body Wellness Collective promotes physical and mental wellbeing. We run morning fitness sessions, stress-management workshops during exam periods and peer-support training in partnership with campus counselling.', 'Morning fitness
Exam-season stress workshops
Peer support training
Wellness walks', 'wellness@example.org', 'Tuesdays & Thursdays, 06:30, Sports Field', 'No sign-up needed — join any session.', 2021, 75, '#1F7466');
INSERT INTO organization_interests VALUES (11, 'health');
INSERT INTO organizations (id, name, short_name, category, summary, description, activities, contact_email, meeting_info, membership, founded_year, member_count, color) VALUES (12, 'Campus Football Club', 'CFC', 'sports', 'Recreational and competitive football for all skill levels.', 'Campus Football Club runs recreational games and competitive teams that play in inter-university leagues. Training is open to all skill levels, and we organise a friendly inter-year tournament every semester.', 'Recreational games
League teams
Inter-year tournament
Fitness training', 'football@example.org', 'Wednesdays & Saturdays, 16:30, Sports Field', 'Turn up to training. League team selection happens at the start of each season.', 2010, 64, '#2E7D32');
INSERT INTO organization_interests VALUES (12, 'health');
INSERT INTO organization_interests VALUES (12, 'leadership');
INSERT INTO organizations (id, name, short_name, category, summary, description, activities, contact_email, meeting_info, membership, founded_year, member_count, color) VALUES (13, 'Pan-African Arts Society', 'PAAS', 'cultural', 'Celebrates African music, dance, film and literature through performances and workshops.', 'The Pan-African Arts Society celebrates the creative traditions of the continent. We host dance and drumming workshops, film screenings with discussions, poetry nights and an annual cultural showcase.', 'Dance & drumming workshops
Film screenings
Poetry nights
Annual cultural showcase', 'paas@example.org', 'Fridays, 19:00, Amphitheatre', 'Everyone is welcome. Performers can audition for the showcase each year.', 2013, 88, '#C0392B');
INSERT INTO organization_interests VALUES (13, 'arts');
INSERT INTO organization_interests VALUES (13, 'social-impact');
INSERT INTO organizations (id, name, short_name, category, summary, description, activities, contact_email, meeting_info, membership, founded_year, member_count, color) VALUES (14, 'Student Media House', 'SMH', 'arts', 'Student newspaper, podcast and video team covering campus life.', 'Student Media House publishes the student newspaper, produces a weekly podcast and creates short video features about campus life. Members learn writing, editing, audio and video production, and editorial leadership.', 'Student newspaper
Weekly podcast
Video features
Editorial training', 'media@example.org', 'Mondays, 17:00, Media Room', 'Submit a short writing, audio or video sample to join a desk.', 2015, 42, '#34495E');
INSERT INTO organization_interests VALUES (14, 'arts');
INSERT INTO organization_interests VALUES (14, 'leadership');
INSERT INTO organizations (id, name, short_name, category, summary, description, activities, contact_email, meeting_info, membership, founded_year, member_count, color) VALUES (15, 'Global Health Society', 'GHS', 'professional', 'Explores public health challenges through talks, case competitions and community projects.', 'Global Health Society brings together students interested in public health, medicine and health systems. We host expert talks, run health case competitions and support community health projects with partner organisations.', 'Expert talks
Health case competitions
Community health projects
Journal club', 'globalhealth@example.org', 'Every other Tuesday, 18:00, Library Seminar Room', 'Sign up at any event to join the mailing list and project teams.', 2018, 51, '#A23E8C');
INSERT INTO organization_interests VALUES (15, 'health');
INSERT INTO organization_interests VALUES (15, 'research');
INSERT INTO organization_interests VALUES (15, 'social-impact');
INSERT INTO organizations (id, name, short_name, category, summary, description, activities, contact_email, meeting_info, membership, founded_year, member_count, color) VALUES (16, 'Career Readiness Network', 'CRN', 'professional', 'CV clinics, mock interviews and employer sessions to prepare for internships and jobs.', 'Career Readiness Network helps students prepare for internships and graduate roles. We run CV and cover letter clinics, mock interviews with volunteers from industry and sessions on navigating the job market.', 'CV clinics
Mock interviews
Employer sessions
LinkedIn workshops', 'careers@example.org', 'Wednesdays, 17:00, Career Centre', 'Open to all students — just register for any session.', 2016, 120, '#5D6D7E');
INSERT INTO organization_interests VALUES (16, 'business');
INSERT INTO organization_interests VALUES (16, 'leadership');
INSERT INTO organization_interests VALUES (16, 'computer-science');

-- ---------------------------------------------------------------- opportunities
INSERT INTO opportunities (id, title, provider, organization_id, category, summary, description, eligibility, location_mode, location_place, deadline, published_at, source_name, source_url, status) VALUES (1, 'Volta STEM Undergraduate Scholarship', 'Volta Learning Foundation (fictional)', NULL, 'scholarship', 'Partial tuition support and mentorship for second- and third-year STEM students.', 'The Volta STEM Undergraduate Scholarship supports students in science, technology, engineering and mathematics with partial tuition funding for one academic year, plus quarterly mentorship sessions with practising engineers and scientists. Recipients are expected to take part in one community STEM outreach activity during the year.', 'Second- or third-year undergraduate
Enrolled in a STEM major
Minimum CGPA of 3.0 or equivalent', 'remote', NULL, CURDATE() + INTERVAL 12 DAY + INTERVAL '23:59' HOUR_MINUTE, CURDATE() - INTERVAL 20 DAY + INTERVAL '09:00' HOUR_MINUTE, 'Volta Learning Foundation', 'https://example.org/volta-stem-scholarship', 'published');
INSERT INTO opportunity_interests VALUES (1, 'computer-science');
INSERT INTO opportunity_interests VALUES (1, 'engineering');
INSERT INTO opportunity_interests VALUES (1, 'research');
INSERT INTO opportunities (id, title, provider, organization_id, category, summary, description, eligibility, location_mode, location_place, deadline, published_at, source_name, source_url, status) VALUES (2, 'Software Engineering Internship — Kente Digital', 'Kente Digital (fictional)', NULL, 'internship', 'Ten-week paid internship building web and mobile products with a product team in Accra.', 'Interns join a cross-functional product team for ten weeks and ship features to real users. You will work with React and TypeScript on the web, contribute to code reviews and present your work at a final demo day. Each intern is paired with an engineering mentor.', 'Penultimate-year Computer Science or related student
Experience with at least one programming language
Available for the full ten weeks', 'in-person', 'Accra', CURDATE() + INTERVAL 5 DAY + INTERVAL '23:59' HOUR_MINUTE, CURDATE() - INTERVAL 25 DAY + INTERVAL '09:00' HOUR_MINUTE, 'Kente Digital Careers', 'https://example.org/kente-digital/internships', 'published');
INSERT INTO opportunity_interests VALUES (2, 'computer-science');
INSERT INTO opportunity_interests VALUES (2, 'engineering');
INSERT INTO opportunities (id, title, provider, organization_id, category, summary, description, eligibility, location_mode, location_place, deadline, published_at, source_name, source_url, status) VALUES (3, 'Savannah Climate Leaders Fellowship', 'Savannah Climate Network (fictional)', NULL, 'fellowship', 'Six-month fellowship for students designing community climate adaptation projects.', 'Fellows receive training in climate science, project design and community engagement, then design and pilot a small adaptation project with mentorship. The fellowship includes a stipend for project materials and a final convening with other fellows from across West Africa.', 'Undergraduate in any discipline
Demonstrated interest in climate or sustainability
Commitment of about 5 hours per week', 'hybrid', 'Tamale & online', CURDATE() + INTERVAL 26 DAY + INTERVAL '23:59' HOUR_MINUTE, CURDATE() - INTERVAL 10 DAY + INTERVAL '09:00' HOUR_MINUTE, 'Savannah Climate Network', 'https://example.org/savannah-climate-fellowship', 'published');
INSERT INTO opportunity_interests VALUES (3, 'sustainability');
INSERT INTO opportunity_interests VALUES (3, 'social-impact');
INSERT INTO opportunity_interests VALUES (3, 'leadership');
INSERT INTO opportunities (id, title, provider, organization_id, category, summary, description, eligibility, location_mode, location_place, deadline, published_at, source_name, source_url, status) VALUES (4, 'Build for Campus Hackathon', 'Code & Circuits Society', 1, 'hackathon', 'A 36-hour hackathon to build tools that improve everyday student life.', 'Form a team of up to four and build a working prototype that solves a real campus problem — from transport to food to study spaces. Mentors are available throughout, and the top three teams receive support to keep developing their projects.', 'Any enrolled student
Teams of 2–4 people
Beginners welcome', 'on-campus', 'Engineering Building', CURDATE() + INTERVAL 9 DAY + INTERVAL '23:59' HOUR_MINUTE, CURDATE() - INTERVAL 7 DAY + INTERVAL '09:00' HOUR_MINUTE, 'Code & Circuits Society', 'https://example.org/code-circuits/hackathon', 'published');
INSERT INTO opportunity_interests VALUES (4, 'computer-science');
INSERT INTO opportunity_interests VALUES (4, 'engineering');
INSERT INTO opportunity_interests VALUES (4, 'entrepreneurship');
INSERT INTO opportunities (id, title, provider, organization_id, category, summary, description, eligibility, location_mode, location_place, deadline, published_at, source_name, source_url, status) VALUES (5, 'Student Ventures Pitch Competition', 'Venture Lab Collective', 2, 'competition', 'Pitch your early-stage venture to a panel of founders for seed support and mentorship.', 'Teams submit a two-page summary and a three-minute video pitch. Finalists present live to a panel of founders and investors. Winners receive seed support, a semester of mentorship and workspace at the Innovation Hub.', 'Teams with at least one enrolled student
Venture at idea or early-prototype stage', 'on-campus', 'Innovation Hub', CURDATE() + INTERVAL 18 DAY + INTERVAL '23:59' HOUR_MINUTE, CURDATE() - INTERVAL 14 DAY + INTERVAL '09:00' HOUR_MINUTE, 'Venture Lab Collective', 'https://example.org/venture-lab/pitch', 'published');
INSERT INTO opportunity_interests VALUES (5, 'entrepreneurship');
INSERT INTO opportunity_interests VALUES (5, 'business');
INSERT INTO opportunities (id, title, provider, organization_id, category, summary, description, eligibility, location_mode, location_place, deadline, published_at, source_name, source_url, status) VALUES (6, 'Research Assistant — Community Health Survey', 'Coastal Health Research Unit (fictional)', NULL, 'research', 'Assist with data collection and analysis for a community health survey over one semester.', 'Research assistants support a team studying access to primary healthcare. Tasks include survey design review, data cleaning and basic statistical analysis in R or Python. Training in research ethics is provided before fieldwork begins.', 'Undergraduate with basic statistics
Interest in public health
Available 8 hours per week', 'hybrid', 'Cape Coast & online', CURDATE() + INTERVAL 15 DAY + INTERVAL '23:59' HOUR_MINUTE, CURDATE() - INTERVAL 12 DAY + INTERVAL '09:00' HOUR_MINUTE, 'Coastal Health Research Unit', 'https://example.org/coastal-health/ra', 'published');
INSERT INTO opportunity_interests VALUES (6, 'research');
INSERT INTO opportunity_interests VALUES (6, 'health');
INSERT INTO opportunities (id, title, provider, organization_id, category, summary, description, eligibility, location_mode, location_place, deadline, published_at, source_name, source_url, status) VALUES (7, 'Weekend Literacy Volunteers', 'Community Outreach Corps', 8, 'volunteering', 'Support reading clubs for primary school pupils on Saturday mornings.', 'Volunteers lead small reading groups for pupils at a nearby primary school. You will receive a short orientation and a reading toolkit. Transport is arranged from campus each Saturday.', 'Any enrolled student
Commit to at least four Saturdays', 'in-person', 'Nearby community school', NULL, CURDATE() - INTERVAL 30 DAY + INTERVAL '09:00' HOUR_MINUTE, 'Community Outreach Corps', 'https://example.org/outreach/literacy', 'published');
INSERT INTO opportunity_interests VALUES (7, 'social-impact');
INSERT INTO opportunity_interests VALUES (7, 'leadership');
INSERT INTO opportunities (id, title, provider, organization_id, category, summary, description, eligibility, location_mode, location_place, deadline, published_at, source_name, source_url, status) VALUES (8, 'Emerging Leaders Programme', 'West Africa Leadership Institute (fictional)', NULL, 'leadership', 'A twelve-week leadership programme with coaching, workshops and a team service project.', 'Participants attend fortnightly workshops on ethical leadership, communication and team management, receive one-on-one coaching and complete a team service project with a local organisation. The programme ends with a public showcase.', 'First- to third-year undergraduate
Evidence of leadership in any setting
Two-page personal statement', 'remote', NULL, CURDATE() + INTERVAL 33 DAY + INTERVAL '23:59' HOUR_MINUTE, CURDATE() - INTERVAL 6 DAY + INTERVAL '09:00' HOUR_MINUTE, 'West Africa Leadership Institute', 'https://example.org/wali/emerging-leaders', 'published');
INSERT INTO opportunity_interests VALUES (8, 'leadership');
INSERT INTO opportunity_interests VALUES (8, 'social-impact');
INSERT INTO opportunities (id, title, provider, organization_id, category, summary, description, eligibility, location_mode, location_place, deadline, published_at, source_name, source_url, status) VALUES (9, 'Data Science Career Bootcamp', 'Career Readiness Network', 16, 'career', 'Four Saturday sessions on data analysis portfolios, interviews and job search strategy.', 'This bootcamp helps students prepare for data roles. Sessions cover building a portfolio project, SQL and Python interview practice, and how to present your work. Each participant receives individual CV feedback.', 'Any enrolled student
Basic programming experience recommended', 'on-campus', 'Career Centre', CURDATE() + INTERVAL 7 DAY + INTERVAL '23:59' HOUR_MINUTE, CURDATE() - INTERVAL 9 DAY + INTERVAL '09:00' HOUR_MINUTE, 'Career Readiness Network', 'https://example.org/career-network/data-bootcamp', 'published');
INSERT INTO opportunity_interests VALUES (9, 'computer-science');
INSERT INTO opportunity_interests VALUES (9, 'business');
INSERT INTO opportunities (id, title, provider, organization_id, category, summary, description, eligibility, location_mode, location_place, deadline, published_at, source_name, source_url, status) VALUES (10, 'Renewable Energy Engineering Internship', 'SunGrid Solutions (fictional)', NULL, 'internship', 'Field and design internship on solar mini-grid installations.', 'Interns support the design and monitoring of solar mini-grids for rural communities. You will assist with site surveys, load calculations and performance monitoring dashboards.', 'Engineering student in second year or above
Willingness to travel for site visits', 'in-person', 'Kumasi', CURDATE() + INTERVAL 21 DAY + INTERVAL '23:59' HOUR_MINUTE, CURDATE() - INTERVAL 15 DAY + INTERVAL '09:00' HOUR_MINUTE, 'SunGrid Solutions', 'https://example.org/sungrid/internships', 'published');
INSERT INTO opportunity_interests VALUES (10, 'engineering');
INSERT INTO opportunity_interests VALUES (10, 'sustainability');
INSERT INTO opportunities (id, title, provider, organization_id, category, summary, description, eligibility, location_mode, location_place, deadline, published_at, source_name, source_url, status) VALUES (11, 'Student Creative Arts Micro-Grant', 'Lagoon Arts Trust (fictional)', NULL, 'scholarship', 'Small grants for students producing film, music, visual art or writing projects.', 'The micro-grant funds materials and equipment for student creative projects. Applicants submit a short project proposal, a budget and examples of previous work. Grantees exhibit or perform their work at the end of the semester.', 'Any enrolled student
Project completed within one semester', 'remote', NULL, CURDATE() + INTERVAL 40 DAY + INTERVAL '23:59' HOUR_MINUTE, CURDATE() - INTERVAL 4 DAY + INTERVAL '09:00' HOUR_MINUTE, 'Lagoon Arts Trust', 'https://example.org/lagoon-arts/micro-grant', 'published');
INSERT INTO opportunity_interests VALUES (11, 'arts');
INSERT INTO opportunities (id, title, provider, organization_id, category, summary, description, eligibility, location_mode, location_place, deadline, published_at, source_name, source_url, status) VALUES (12, 'Fintech Case Challenge', 'Finance & Investment Club', 7, 'competition', 'Teams analyse a mobile-money business case and present recommendations to judges.', 'Teams of three receive a business case on a mobile-money product and have one week to prepare a strategy recommendation. Finalists present to judges from finance and technology backgrounds.', 'Teams of three enrolled students
All majors welcome', 'on-campus', 'Business Wing', CURDATE() + INTERVAL 11 DAY + INTERVAL '23:59' HOUR_MINUTE, CURDATE() - INTERVAL 8 DAY + INTERVAL '09:00' HOUR_MINUTE, 'Finance & Investment Club', 'https://example.org/finance-club/case-challenge', 'published');
INSERT INTO opportunity_interests VALUES (12, 'business');
INSERT INTO opportunity_interests VALUES (12, 'entrepreneurship');
INSERT INTO opportunity_interests VALUES (12, 'computer-science');
INSERT INTO opportunities (id, title, provider, organization_id, category, summary, description, eligibility, location_mode, location_place, deadline, published_at, source_name, source_url, status) VALUES (13, 'Undergraduate AI Research Fellowship', 'Institute for Applied Computing (fictional)', NULL, 'research', 'Summer research on machine learning for agriculture and health, with a faculty mentor.', 'Fellows spend eight weeks on a supervised research project applying machine learning to agriculture or health datasets. The fellowship includes a stipend, a reading group and a final research poster presentation.', 'Third-year Computer Science, Engineering or Mathematics student
Coursework in statistics or machine learning
Transcript and one reference letter', 'hybrid', 'Accra & online', CURDATE() + INTERVAL 48 DAY + INTERVAL '23:59' HOUR_MINUTE, CURDATE() - INTERVAL 3 DAY + INTERVAL '09:00' HOUR_MINUTE, 'Institute for Applied Computing', 'https://example.org/iac/research-fellowship', 'published');
INSERT INTO opportunity_interests VALUES (13, 'computer-science');
INSERT INTO opportunity_interests VALUES (13, 'research');
INSERT INTO opportunities (id, title, provider, organization_id, category, summary, description, eligibility, location_mode, location_place, deadline, published_at, source_name, source_url, status) VALUES (14, 'Social Enterprise Fellowship', 'Harmattan Impact Fund (fictional)', NULL, 'fellowship', 'Nine-month support for student-led ventures tackling social challenges.', 'Fellows receive monthly workshops, a dedicated mentor and a small grant to test a social venture. The fellowship focuses on measuring impact and building sustainable revenue models.', 'Student-led venture with a social mission
At least one team member enrolled full-time', 'remote', NULL, CURDATE() + INTERVAL 29 DAY + INTERVAL '23:59' HOUR_MINUTE, CURDATE() - INTERVAL 11 DAY + INTERVAL '09:00' HOUR_MINUTE, 'Harmattan Impact Fund', 'https://example.org/harmattan/fellowship', 'published');
INSERT INTO opportunity_interests VALUES (14, 'entrepreneurship');
INSERT INTO opportunity_interests VALUES (14, 'social-impact');
INSERT INTO opportunity_interests VALUES (14, 'business');
INSERT INTO opportunities (id, title, provider, organization_id, category, summary, description, eligibility, location_mode, location_place, deadline, published_at, source_name, source_url, status) VALUES (15, 'UX Design Internship', 'Baobab Studio (fictional)', NULL, 'internship', 'Remote UX internship designing interfaces for financial inclusion products.', 'Interns run user interviews, create wireframes and prototypes, and test designs with real users. You will work closely with a senior designer and present a case study at the end of the internship.', 'Any major with a design portfolio
Familiarity with a prototyping tool', 'remote', NULL, CURDATE() + INTERVAL 16 DAY + INTERVAL '23:59' HOUR_MINUTE, CURDATE() - INTERVAL 13 DAY + INTERVAL '09:00' HOUR_MINUTE, 'Baobab Studio', 'https://example.org/baobab/ux-internship', 'published');
INSERT INTO opportunity_interests VALUES (15, 'arts');
INSERT INTO opportunity_interests VALUES (15, 'computer-science');
INSERT INTO opportunity_interests VALUES (15, 'business');
INSERT INTO opportunities (id, title, provider, organization_id, category, summary, description, eligibility, location_mode, location_place, deadline, published_at, source_name, source_url, status) VALUES (16, 'Regional Student Robotics Challenge', 'Robotics & Automation Team', 10, 'competition', 'Build an autonomous robot to complete an agricultural sorting task.', 'Teams design and program an autonomous robot that sorts produce by size and colour. The challenge rewards reliable engineering, documentation and teamwork. Fabrication lab time is available to registered teams.', 'Teams of 3–5 students
At least one engineering student per team', 'on-campus', 'Fabrication Lab', CURDATE() + INTERVAL 24 DAY + INTERVAL '23:59' HOUR_MINUTE, CURDATE() - INTERVAL 16 DAY + INTERVAL '09:00' HOUR_MINUTE, 'Robotics & Automation Team', 'https://example.org/robotics/challenge', 'published');
INSERT INTO opportunity_interests VALUES (16, 'engineering');
INSERT INTO opportunity_interests VALUES (16, 'computer-science');
INSERT INTO opportunities (id, title, provider, organization_id, category, summary, description, eligibility, location_mode, location_place, deadline, published_at, source_name, source_url, status) VALUES (17, 'Health Screening Day Volunteers', 'Global Health Society', 15, 'volunteering', 'Help organise a free community health screening day with partner clinics.', 'Volunteers help with registration, queue management and health education stations during a community screening day. Training is provided the week before the event.', 'Any enrolled student
Attend one training session', 'in-person', 'Community centre near campus', CURDATE() + INTERVAL 3 DAY + INTERVAL '23:59' HOUR_MINUTE, CURDATE() - INTERVAL 10 DAY + INTERVAL '09:00' HOUR_MINUTE, 'Global Health Society', 'https://example.org/global-health/screening-day', 'published');
INSERT INTO opportunity_interests VALUES (17, 'health');
INSERT INTO opportunity_interests VALUES (17, 'social-impact');
INSERT INTO opportunities (id, title, provider, organization_id, category, summary, description, eligibility, location_mode, location_place, deadline, published_at, source_name, source_url, status) VALUES (18, 'Women in Technology Scholarship', 'Akwaaba Tech Trust (fictional)', NULL, 'scholarship', 'Full tuition scholarship and laptop for women studying computing or engineering.', 'The scholarship covers tuition for one academic year and includes a laptop and a mentor from the technology industry. Scholars join a cohort that meets monthly online.', 'Woman enrolled in Computer Science, Information Systems or Engineering
Demonstrated financial need
Essay of up to 800 words', 'remote', NULL, CURDATE() + INTERVAL 35 DAY + INTERVAL '23:59' HOUR_MINUTE, CURDATE() - INTERVAL 18 DAY + INTERVAL '09:00' HOUR_MINUTE, 'Akwaaba Tech Trust', 'https://example.org/akwaaba/scholarship', 'published');
INSERT INTO opportunity_interests VALUES (18, 'computer-science');
INSERT INTO opportunity_interests VALUES (18, 'engineering');
INSERT INTO opportunity_interests VALUES (18, 'leadership');
INSERT INTO opportunities (id, title, provider, organization_id, category, summary, description, eligibility, location_mode, location_place, deadline, published_at, source_name, source_url, status) VALUES (19, 'Student Journalism Fellowship', 'Independent Press Foundation (fictional)', NULL, 'fellowship', 'Mentored reporting fellowship producing in-depth stories on education and development.', 'Fellows are paired with an experienced editor and produce two in-depth stories over a semester. Workshops cover interviewing, fact-checking, data journalism and media ethics.', 'Any enrolled student
Two writing samples', 'remote', NULL, CURDATE() + INTERVAL 19 DAY + INTERVAL '23:59' HOUR_MINUTE, CURDATE() - INTERVAL 5 DAY + INTERVAL '09:00' HOUR_MINUTE, 'Independent Press Foundation', 'https://example.org/ipf/fellowship', 'published');
INSERT INTO opportunity_interests VALUES (19, 'arts');
INSERT INTO opportunity_interests VALUES (19, 'social-impact');
INSERT INTO opportunities (id, title, provider, organization_id, category, summary, description, eligibility, location_mode, location_place, deadline, published_at, source_name, source_url, status) VALUES (20, 'Supply Chain Analyst Internship', 'Harbour Logistics (fictional)', NULL, 'internship', 'Analyse shipment data and help optimise warehouse operations.', 'Interns support the operations analytics team by cleaning shipment data, building dashboards and recommending process improvements. Strong spreadsheet skills are essential; SQL is a plus.', 'Business, Engineering or Economics student
Strong spreadsheet skills', 'in-person', 'Tema', CURDATE() + INTERVAL 2 DAY + INTERVAL '23:59' HOUR_MINUTE, CURDATE() - INTERVAL 28 DAY + INTERVAL '09:00' HOUR_MINUTE, 'Harbour Logistics', 'https://example.org/harbour/internships', 'published');
INSERT INTO opportunity_interests VALUES (20, 'business');
INSERT INTO opportunity_interests VALUES (20, 'engineering');
INSERT INTO opportunities (id, title, provider, organization_id, category, summary, description, eligibility, location_mode, location_place, deadline, published_at, source_name, source_url, status) VALUES (21, 'Campus Energy Audit Volunteers', 'Green Campus Initiative', 3, 'volunteering', 'Measure energy use in campus buildings and propose savings.', 'Volunteers learn to use simple energy meters and record usage across campus buildings. The team analyses the data and presents recommendations to facilities management at the end of the semester.', 'Any enrolled student
About 3 hours per week', 'on-campus', NULL, NULL, CURDATE() - INTERVAL 21 DAY + INTERVAL '09:00' HOUR_MINUTE, 'Green Campus Initiative', 'https://example.org/green-campus/energy-audit', 'published');
INSERT INTO opportunity_interests VALUES (21, 'sustainability');
INSERT INTO opportunity_interests VALUES (21, 'engineering');
INSERT INTO opportunity_interests VALUES (21, 'research');
INSERT INTO opportunities (id, title, provider, organization_id, category, summary, description, eligibility, location_mode, location_place, deadline, published_at, source_name, source_url, status) VALUES (22, 'Alumni Career Mentorship Programme', 'Women in STEM Network', 6, 'career', 'Semester-long one-on-one mentorship with alumni working in technology and science.', 'Mentees are matched with an alumni mentor based on career interests and meet at least monthly. The programme includes a kickoff workshop on goal setting and a closing reflection session.', 'Any enrolled student in a STEM field
Commit to monthly meetings', 'hybrid', NULL, CURDATE() + INTERVAL 13 DAY + INTERVAL '23:59' HOUR_MINUTE, CURDATE() - INTERVAL 6 DAY + INTERVAL '09:00' HOUR_MINUTE, 'Women in STEM Network', 'https://example.org/wistem/mentorship', 'published');
INSERT INTO opportunity_interests VALUES (22, 'computer-science');
INSERT INTO opportunity_interests VALUES (22, 'engineering');
INSERT INTO opportunity_interests VALUES (22, 'leadership');
INSERT INTO opportunities (id, title, provider, organization_id, category, summary, description, eligibility, location_mode, location_place, deadline, published_at, source_name, source_url, status) VALUES (23, 'AgriTech Innovation Hackathon', 'Northern Agri Innovation Hub (fictional)', NULL, 'hackathon', 'Weekend hackathon building digital tools for smallholder farmers.', 'Teams work with farmer cooperatives to understand challenges, then prototype digital solutions over a weekend. Travel support is available for selected student teams.', 'Teams of 3–5
University students from any discipline', 'in-person', 'Tamale', CURDATE() + INTERVAL 44 DAY + INTERVAL '23:59' HOUR_MINUTE, CURDATE() - INTERVAL 2 DAY + INTERVAL '09:00' HOUR_MINUTE, 'Northern Agri Innovation Hub', 'https://example.org/agri-hub/hackathon', 'published');
INSERT INTO opportunity_interests VALUES (23, 'computer-science');
INSERT INTO opportunity_interests VALUES (23, 'sustainability');
INSERT INTO opportunity_interests VALUES (23, 'entrepreneurship');
INSERT INTO opportunities (id, title, provider, organization_id, category, summary, description, eligibility, location_mode, location_place, deadline, published_at, source_name, source_url, status) VALUES (24, 'Model United Nations Delegation', 'Hillside Debate Union', 5, 'leadership', 'Represent the university at a regional Model UN conference.', 'Selected delegates prepare position papers and represent assigned countries in committee simulations. Training sessions cover diplomacy, negotiation and resolution writing.', 'Any enrolled student
Attend all preparation sessions', 'in-person', 'Accra', CURDATE() + INTERVAL 8 DAY + INTERVAL '23:59' HOUR_MINUTE, CURDATE() - INTERVAL 12 DAY + INTERVAL '09:00' HOUR_MINUTE, 'Hillside Debate Union', 'https://example.org/debate-union/mun', 'published');
INSERT INTO opportunity_interests VALUES (24, 'leadership');
INSERT INTO opportunity_interests VALUES (24, 'social-impact');
INSERT INTO opportunities (id, title, provider, organization_id, category, summary, description, eligibility, location_mode, location_place, deadline, published_at, source_name, source_url, status) VALUES (25, 'Essay Competition on African Futures', 'Futures Thinking Forum (fictional)', NULL, 'competition', 'Essay competition on technology and society in Africa in 2050.', 'Students write an essay of up to 2,000 words imagining how technology could shape African societies by 2050. Winning essays are published in an online anthology.', 'Any enrolled undergraduate
Original work only', 'remote', NULL, CURDATE() - INTERVAL 4 DAY + INTERVAL '23:59' HOUR_MINUTE, CURDATE() - INTERVAL 40 DAY + INTERVAL '09:00' HOUR_MINUTE, 'Futures Thinking Forum', 'https://example.org/futures-forum/essay', 'published');
INSERT INTO opportunity_interests VALUES (25, 'arts');
INSERT INTO opportunity_interests VALUES (25, 'social-impact');
INSERT INTO opportunity_interests VALUES (25, 'research');
INSERT INTO opportunities (id, title, provider, organization_id, category, summary, description, eligibility, location_mode, location_place, deadline, published_at, source_name, source_url, status) VALUES (26, 'Student Podcast Producer Role', 'Student Media House', 14, 'leadership', 'Lead production of the weekly campus podcast for one semester.', 'The producer plans episodes, coordinates hosts and guests, and oversees editing. Training on audio equipment and editing software is provided.', 'Any enrolled student
About 5 hours per week', 'on-campus', 'Media Room', CURDATE() + INTERVAL 6 DAY + INTERVAL '23:59' HOUR_MINUTE, CURDATE() - INTERVAL 4 DAY + INTERVAL '09:00' HOUR_MINUTE, 'Student Media House', 'https://example.org/media-house/podcast', 'published');
INSERT INTO opportunity_interests VALUES (26, 'arts');
INSERT INTO opportunity_interests VALUES (26, 'leadership');
INSERT INTO opportunities (id, title, provider, organization_id, category, summary, description, eligibility, location_mode, location_place, deadline, published_at, source_name, source_url, status) VALUES (27, 'Cloud Skills Certification Scholarship', 'Skyline Learning (fictional)', NULL, 'scholarship', 'Covers the cost of an entry-level cloud certification exam and study materials.', 'Selected students receive study materials and an exam voucher for an entry-level cloud certification. Study groups meet online weekly.', 'Any enrolled student
Basic computing knowledge', 'remote', NULL, CURDATE() + INTERVAL 30 DAY + INTERVAL '23:59' HOUR_MINUTE, CURDATE() - INTERVAL 1 DAY + INTERVAL '09:00' HOUR_MINUTE, 'Skyline Learning', 'https://example.org/skyline/scholarship', 'pending');
INSERT INTO opportunity_interests VALUES (27, 'computer-science');
INSERT INTO opportunities (id, title, provider, organization_id, category, summary, description, eligibility, location_mode, location_place, deadline, published_at, source_name, source_url, status) VALUES (28, 'Zero-Waste Campus Challenge', 'Green Campus Initiative', 3, 'competition', 'Teams compete to design the most effective waste-reduction intervention.', 'Teams propose and pilot a waste-reduction intervention in a campus space for two weeks, measuring results. The most effective intervention is adopted campus-wide.', 'Teams of 2–4 enrolled students', 'on-campus', NULL, CURDATE() + INTERVAL 22 DAY + INTERVAL '23:59' HOUR_MINUTE, CURDATE() + INTERVAL 0 DAY + INTERVAL '09:00' HOUR_MINUTE, 'Green Campus Initiative', 'https://example.org/green-campus/zero-waste', 'pending');
INSERT INTO opportunity_interests VALUES (28, 'sustainability');
INSERT INTO opportunity_interests VALUES (28, 'social-impact');
INSERT INTO opportunities (id, title, provider, organization_id, category, summary, description, eligibility, location_mode, location_place, deadline, published_at, source_name, source_url, status) VALUES (29, 'Quick Cash Online Survey Jobs', 'Unknown poster', NULL, 'career', 'Earn money filling in surveys online. Pay a small fee to register.', 'This listing asks students to pay a registration fee before receiving survey work. It was flagged for review because it lacks a verifiable provider.', '', 'remote', NULL, CURDATE() + INTERVAL 3 DAY + INTERVAL '23:59' HOUR_MINUTE, CURDATE() + INTERVAL 0 DAY + INTERVAL '09:00' HOUR_MINUTE, 'Unverified website', 'https://example.org/unverified-listing', 'pending');
INSERT INTO opportunity_interests VALUES (29, 'business');

-- ---------------------------------------------------------------- events
INSERT INTO events (id, title, organization_id, category, summary, description, venue, starts_at, ends_at, capacity, registration, contact, status) VALUES (1, 'Intro to Web Development Workshop', 1, 'tech', 'Build and publish your first web page in two hours. No experience needed.', 'A hands-on workshop for complete beginners. You will learn the basics of HTML and CSS, build a simple personal page and publish it. Bring a laptop; mentors will help you set up.', 'Engineering Lab 2', CURDATE() + INTERVAL 2 DAY + INTERVAL '18:30' HOUR_MINUTE, CURDATE() + INTERVAL 2 DAY + INTERVAL '18:30' HOUR_MINUTE + INTERVAL 120 MINUTE, 40, 'rsvp', 'codecircuits@example.org', 'published');
INSERT INTO event_interests VALUES (1, 'computer-science');
INSERT INTO events (id, title, organization_id, category, summary, description, venue, starts_at, ends_at, capacity, registration, contact, status) VALUES (2, 'Pitch Clinic: Tell Your Story in 3 Minutes', 2, 'career', 'Practise your pitch and get feedback from founders before the pitch competition.', 'Bring a three-minute pitch for any idea. Each participant pitches and receives structured feedback from founders and peers. Ideal preparation for the Student Ventures Pitch Competition.', 'Innovation Hub', CURDATE() + INTERVAL 4 DAY + INTERVAL '17:00' HOUR_MINUTE, CURDATE() + INTERVAL 4 DAY + INTERVAL '17:00' HOUR_MINUTE + INTERVAL 120 MINUTE, 30, 'rsvp', 'venturelab@example.org', 'published');
INSERT INTO event_interests VALUES (2, 'entrepreneurship');
INSERT INTO event_interests VALUES (2, 'business');
INSERT INTO events (id, title, organization_id, category, summary, description, venue, starts_at, ends_at, capacity, registration, contact, status) VALUES (3, 'Paper Reading Group: Machine Learning for Health', 4, 'academic', 'Discuss a recent paper on machine learning for diagnosing illness in low-resource settings.', 'We read and discuss one paper in depth. A facilitator introduces the methods and we discuss strengths, limitations and ethical questions. The paper is shared a week in advance.', 'Library Seminar Room', CURDATE() + INTERVAL 4 DAY + INTERVAL '18:00' HOUR_MINUTE, CURDATE() + INTERVAL 4 DAY + INTERVAL '18:00' HOUR_MINUTE + INTERVAL 90 MINUTE, 25, 'open', 'researchcircle@example.org', 'published');
INSERT INTO event_interests VALUES (3, 'research');
INSERT INTO event_interests VALUES (3, 'computer-science');
INSERT INTO event_interests VALUES (3, 'health');
INSERT INTO events (id, title, organization_id, category, summary, description, venue, starts_at, ends_at, capacity, registration, contact, status) VALUES (4, 'CV & Cover Letter Clinic', 16, 'career', 'Get one-on-one feedback on your CV before internship deadlines.', 'Volunteers from industry and the Career Readiness Network review CVs and cover letters one-on-one. Bring a printed or digital copy. Sessions are 15 minutes each on a first-come basis.', 'Career Centre', CURDATE() + INTERVAL 1 DAY + INTERVAL '17:00' HOUR_MINUTE, CURDATE() + INTERVAL 1 DAY + INTERVAL '17:00' HOUR_MINUTE + INTERVAL 120 MINUTE, 32, 'open', 'careers@example.org', 'published');
INSERT INTO event_interests VALUES (4, 'business');
INSERT INTO event_interests VALUES (4, 'leadership');
INSERT INTO event_interests VALUES (4, 'computer-science');
INSERT INTO events (id, title, organization_id, category, summary, description, venue, starts_at, ends_at, capacity, registration, contact, status) VALUES (5, 'Campus Tree Planting Morning', 3, 'community', 'Help plant 100 native trees along the campus walkways.', 'Join volunteers to plant native trees along campus walkways. Gloves, tools and refreshments are provided. Wear comfortable clothes and bring a water bottle.', 'Main Walkway', CURDATE() + INTERVAL 6 DAY + INTERVAL '07:00' HOUR_MINUTE, CURDATE() + INTERVAL 6 DAY + INTERVAL '07:00' HOUR_MINUTE + INTERVAL 180 MINUTE, 80, 'open', 'greencampus@example.org', 'published');
INSERT INTO event_interests VALUES (5, 'sustainability');
INSERT INTO event_interests VALUES (5, 'social-impact');
INSERT INTO events (id, title, organization_id, category, summary, description, venue, starts_at, ends_at, capacity, registration, contact, status) VALUES (6, 'Public Debate: Should Universities Ban Smartphones in Class?', 5, 'academic', 'A lively public debate followed by an audience vote.', 'Two teams from the Hillside Debate Union argue this motion in British Parliamentary style. The audience votes before and after the debate to see whose arguments changed minds.', 'Lecture Hall B', CURDATE() + INTERVAL 8 DAY + INTERVAL '19:00' HOUR_MINUTE, CURDATE() + INTERVAL 8 DAY + INTERVAL '19:00' HOUR_MINUTE + INTERVAL 90 MINUTE, 150, 'open', 'debate@example.org', 'published');
INSERT INTO event_interests VALUES (6, 'leadership');
INSERT INTO event_interests VALUES (6, 'social-impact');
INSERT INTO events (id, title, organization_id, category, summary, description, venue, starts_at, ends_at, capacity, registration, contact, status) VALUES (7, 'Women in Tech Career Panel', 6, 'career', 'Alumni working in software, data and engineering share their career journeys.', 'A panel of alumni discuss their paths into technology careers, how they navigated challenges and what they wish they had known as students. Q&A and informal networking follow.', 'Main Auditorium', CURDATE() + INTERVAL 8 DAY + INTERVAL '18:00' HOUR_MINUTE, CURDATE() + INTERVAL 8 DAY + INTERVAL '18:00' HOUR_MINUTE + INTERVAL 120 MINUTE, 120, 'rsvp', 'wistem@example.org', 'published');
INSERT INTO event_interests VALUES (7, 'computer-science');
INSERT INTO event_interests VALUES (7, 'engineering');
INSERT INTO event_interests VALUES (7, 'leadership');
INSERT INTO events (id, title, organization_id, category, summary, description, venue, starts_at, ends_at, capacity, registration, contact, status) VALUES (8, 'Personal Finance 101: Budgeting as a Student', 7, 'career', 'Practical budgeting, saving and mobile-money tips for students.', 'Learn how to build a simple monthly budget, avoid common money mistakes and start saving. Includes a hands-on budgeting exercise with a template you can keep.', 'Business Wing Room 4', CURDATE() + INTERVAL 3 DAY + INTERVAL '17:30' HOUR_MINUTE, CURDATE() + INTERVAL 3 DAY + INTERVAL '17:30' HOUR_MINUTE + INTERVAL 75 MINUTE, 45, 'open', 'finance@example.org', 'published');
INSERT INTO event_interests VALUES (8, 'business');
INSERT INTO events (id, title, organization_id, category, summary, description, venue, starts_at, ends_at, capacity, registration, contact, status) VALUES (9, 'Volunteer Orientation: Reading Clubs', 8, 'community', 'Required orientation for new reading club volunteers.', 'Learn how our reading clubs work, safeguarding basics and practical activities for young readers. Required before your first Saturday placement.', 'Student Centre Room 3', CURDATE() + INTERVAL 5 DAY + INTERVAL '16:00' HOUR_MINUTE, CURDATE() + INTERVAL 5 DAY + INTERVAL '16:00' HOUR_MINUTE + INTERVAL 60 MINUTE, 40, 'rsvp', 'outreach@example.org', 'published');
INSERT INTO event_interests VALUES (9, 'social-impact');
INSERT INTO event_interests VALUES (9, 'leadership');
INSERT INTO events (id, title, organization_id, category, summary, description, venue, starts_at, ends_at, capacity, registration, contact, status) VALUES (10, 'UX Critique Night', 9, 'arts', 'Bring a design in progress and get constructive feedback from peers.', 'Each participant shares a design — an app screen, poster or website — and receives structured feedback. A short talk on giving useful critique opens the session.', 'Studio Room', CURDATE() + INTERVAL 10 DAY + INTERVAL '16:00' HOUR_MINUTE, CURDATE() + INTERVAL 10 DAY + INTERVAL '16:00' HOUR_MINUTE + INTERVAL 120 MINUTE, 20, 'open', 'designstudio@example.org', 'published');
INSERT INTO event_interests VALUES (10, 'arts');
INSERT INTO event_interests VALUES (10, 'computer-science');
INSERT INTO events (id, title, organization_id, category, summary, description, venue, starts_at, ends_at, capacity, registration, contact, status) VALUES (11, 'Robot Build Day', 10, 'tech', 'Open lab day to start building for the Regional Student Robotics Challenge.', 'Teams and newcomers work in the fabrication lab with guidance from experienced members. Learn basic soldering, motor control and sensor wiring.', 'Fabrication Lab', CURDATE() + INTERVAL 6 DAY + INTERVAL '09:00' HOUR_MINUTE, CURDATE() + INTERVAL 6 DAY + INTERVAL '09:00' HOUR_MINUTE + INTERVAL 300 MINUTE, 24, 'rsvp', 'robotics@example.org', 'published');
INSERT INTO event_interests VALUES (11, 'engineering');
INSERT INTO event_interests VALUES (11, 'computer-science');
INSERT INTO events (id, title, organization_id, category, summary, description, venue, starts_at, ends_at, capacity, registration, contact, status) VALUES (12, 'Managing Exam Stress Workshop', 11, 'wellness', 'Practical techniques for focus, sleep and stress management before exams.', 'A peer-led workshop with simple breathing techniques, study planning tips and information about campus support services.', 'Student Centre Room 1', CURDATE() + INTERVAL 12 DAY + INTERVAL '17:00' HOUR_MINUTE, CURDATE() + INTERVAL 12 DAY + INTERVAL '17:00' HOUR_MINUTE + INTERVAL 60 MINUTE, 50, 'open', 'wellness@example.org', 'published');
INSERT INTO event_interests VALUES (12, 'health');
INSERT INTO events (id, title, organization_id, category, summary, description, venue, starts_at, ends_at, capacity, registration, contact, status) VALUES (13, 'Inter-Year Football Tournament', 12, 'sports', 'Year groups compete in a friendly tournament. Spectators welcome.', 'Teams from each year group play short matches in a round-robin format, followed by a final. Come to play or cheer.', 'Sports Field', CURDATE() + INTERVAL 9 DAY + INTERVAL '14:00' HOUR_MINUTE, CURDATE() + INTERVAL 9 DAY + INTERVAL '14:00' HOUR_MINUTE + INTERVAL 240 MINUTE, NULL, 'open', 'football@example.org', 'published');
INSERT INTO event_interests VALUES (13, 'health');
INSERT INTO events (id, title, organization_id, category, summary, description, venue, starts_at, ends_at, capacity, registration, contact, status) VALUES (14, 'Film Screening & Discussion: African Cinema Classics', 13, 'arts', 'Watch a classic of African cinema and join a facilitated discussion.', 'An evening screening of a celebrated African film followed by a discussion of its themes and history. Snacks provided.', 'Amphitheatre', CURDATE() + INTERVAL 2 DAY + INTERVAL '19:00' HOUR_MINUTE, CURDATE() + INTERVAL 2 DAY + INTERVAL '19:00' HOUR_MINUTE + INTERVAL 150 MINUTE, 100, 'open', 'paas@example.org', 'published');
INSERT INTO event_interests VALUES (14, 'arts');
INSERT INTO event_interests VALUES (14, 'social-impact');
INSERT INTO events (id, title, organization_id, category, summary, description, venue, starts_at, ends_at, capacity, registration, contact, status) VALUES (15, 'Podcast Production Training', 14, 'arts', 'Learn to record and edit audio for the campus podcast.', 'Hands-on training with microphones, recording software and basic editing. Ideal for anyone interested in the Student Podcast Producer role.', 'Media Room', CURDATE() + INTERVAL 3 DAY + INTERVAL '17:00' HOUR_MINUTE, CURDATE() + INTERVAL 3 DAY + INTERVAL '17:00' HOUR_MINUTE + INTERVAL 90 MINUTE, 15, 'rsvp', 'media@example.org', 'published');
INSERT INTO event_interests VALUES (15, 'arts');
INSERT INTO event_interests VALUES (15, 'leadership');
INSERT INTO events (id, title, organization_id, category, summary, description, venue, starts_at, ends_at, capacity, registration, contact, status) VALUES (16, 'Talk: Data and the Future of Public Health', 15, 'academic', 'A public health researcher discusses how data is changing disease prevention.', 'Our guest speaker discusses how data collection and analysis are used to plan health services, with examples from West Africa. Q&A follows the talk.', 'Library Seminar Room', CURDATE() + INTERVAL 11 DAY + INTERVAL '18:00' HOUR_MINUTE, CURDATE() + INTERVAL 11 DAY + INTERVAL '18:00' HOUR_MINUTE + INTERVAL 90 MINUTE, 60, 'open', 'globalhealth@example.org', 'published');
INSERT INTO event_interests VALUES (16, 'health');
INSERT INTO event_interests VALUES (16, 'research');
INSERT INTO events (id, title, organization_id, category, summary, description, venue, starts_at, ends_at, capacity, registration, contact, status) VALUES (17, 'Build for Campus Hackathon — Kickoff', 1, 'tech', 'Opening session: team formation, problem briefs and mentor introductions.', 'The official kickoff of the Build for Campus Hackathon. Hear the problem briefs, meet mentors and finalise your team. Building starts immediately after.', 'Engineering Building Atrium', CURDATE() + INTERVAL 14 DAY + INTERVAL '17:00' HOUR_MINUTE, CURDATE() + INTERVAL 14 DAY + INTERVAL '17:00' HOUR_MINUTE + INTERVAL 120 MINUTE, 120, 'rsvp', 'codecircuits@example.org', 'published');
INSERT INTO event_interests VALUES (17, 'computer-science');
INSERT INTO event_interests VALUES (17, 'engineering');
INSERT INTO event_interests VALUES (17, 'entrepreneurship');
INSERT INTO events (id, title, organization_id, category, summary, description, venue, starts_at, ends_at, capacity, registration, contact, status) VALUES (18, 'Founder Fireside Chat', 2, 'career', 'An informal conversation with a founder about building a company in Ghana.', 'A founder shares honest lessons about starting and growing a company: finding customers, hiring and handling setbacks. Audience questions are encouraged.', 'Innovation Hub', CURDATE() + INTERVAL 15 DAY + INTERVAL '18:00' HOUR_MINUTE, CURDATE() + INTERVAL 15 DAY + INTERVAL '18:00' HOUR_MINUTE + INTERVAL 90 MINUTE, 70, 'open', 'venturelab@example.org', 'published');
INSERT INTO event_interests VALUES (18, 'entrepreneurship');
INSERT INTO event_interests VALUES (18, 'business');
INSERT INTO event_interests VALUES (18, 'leadership');
INSERT INTO events (id, title, organization_id, category, summary, description, venue, starts_at, ends_at, capacity, registration, contact, status) VALUES (19, 'Sunrise Fitness Session', 11, 'wellness', 'A 45-minute group workout for all fitness levels.', 'Start the day with a guided group workout combining cardio, stretching and bodyweight exercises. Suitable for beginners.', 'Sports Field', CURDATE() + INTERVAL 1 DAY + INTERVAL '06:30' HOUR_MINUTE, CURDATE() + INTERVAL 1 DAY + INTERVAL '06:30' HOUR_MINUTE + INTERVAL 45 MINUTE, NULL, 'open', 'wellness@example.org', 'published');
INSERT INTO event_interests VALUES (19, 'health');
INSERT INTO events (id, title, organization_id, category, summary, description, venue, starts_at, ends_at, capacity, registration, contact, status) VALUES (20, 'Undergraduate Research Showcase', 4, 'academic', 'Students present posters and short talks on their research projects.', 'The annual showcase features posters and lightning talks from student researchers across disciplines. Visitors can vote for the people''s choice award.', 'Main Auditorium Foyer', CURDATE() + INTERVAL 21 DAY + INTERVAL '14:00' HOUR_MINUTE, CURDATE() + INTERVAL 21 DAY + INTERVAL '14:00' HOUR_MINUTE + INTERVAL 180 MINUTE, 200, 'open', 'researchcircle@example.org', 'published');
INSERT INTO event_interests VALUES (20, 'research');
INSERT INTO events (id, title, organization_id, category, summary, description, venue, starts_at, ends_at, capacity, registration, contact, status) VALUES (21, 'Cultural Night Showcase', 13, 'arts', 'An evening of music, dance, poetry and fashion from across the continent.', 'Performers from across the student body share music, dance, spoken word and fashion. The biggest arts event of the semester.', 'Main Auditorium', CURDATE() + INTERVAL 25 DAY + INTERVAL '18:30' HOUR_MINUTE, CURDATE() + INTERVAL 25 DAY + INTERVAL '18:30' HOUR_MINUTE + INTERVAL 180 MINUTE, 300, 'rsvp', 'paas@example.org', 'published');
INSERT INTO event_interests VALUES (21, 'arts');
INSERT INTO events (id, title, organization_id, category, summary, description, venue, starts_at, ends_at, capacity, registration, contact, status) VALUES (22, 'Mock Interview Evening', 16, 'career', 'Practise behavioural and technical interviews with volunteers from industry.', 'Book a 30-minute mock interview slot. Interviewers provide written feedback on your answers, communication and preparation.', 'Career Centre', CURDATE() + INTERVAL 10 DAY + INTERVAL '17:00' HOUR_MINUTE, CURDATE() + INTERVAL 10 DAY + INTERVAL '17:00' HOUR_MINUTE + INTERVAL 150 MINUTE, 20, 'rsvp', 'careers@example.org', 'published');
INSERT INTO event_interests VALUES (22, 'business');
INSERT INTO event_interests VALUES (22, 'computer-science');
INSERT INTO event_interests VALUES (22, 'leadership');
INSERT INTO events (id, title, organization_id, category, summary, description, venue, starts_at, ends_at, capacity, registration, contact, status) VALUES (23, 'Git & GitHub for Group Projects', 1, 'tech', 'Learn branching, pull requests and resolving merge conflicts.', 'A practical session on using Git and GitHub for team projects: branching, pull requests, code review and resolving merge conflicts.', 'Engineering Lab 2', CURDATE() - INTERVAL 3 DAY + INTERVAL '18:30' HOUR_MINUTE, CURDATE() - INTERVAL 3 DAY + INTERVAL '18:30' HOUR_MINUTE + INTERVAL 90 MINUTE, 40, 'open', 'codecircuits@example.org', 'published');
INSERT INTO event_interests VALUES (23, 'computer-science');
INSERT INTO events (id, title, organization_id, category, summary, description, venue, starts_at, ends_at, capacity, registration, contact, status) VALUES (24, 'Open Mic Poetry Night', 13, 'arts', 'Share a poem, song or story at an informal open mic.', 'Sign up on the night to perform for up to five minutes. All languages and styles welcome.', 'Student Centre Café', CURDATE() + INTERVAL 13 DAY + INTERVAL '19:30' HOUR_MINUTE, CURDATE() + INTERVAL 13 DAY + INTERVAL '19:30' HOUR_MINUTE + INTERVAL 120 MINUTE, 60, 'open', 'paas@example.org', 'pending');
INSERT INTO event_interests VALUES (24, 'arts');
INSERT INTO events (id, title, organization_id, category, summary, description, venue, starts_at, ends_at, capacity, registration, contact, status) VALUES (25, 'Coastal Clean-up Trip', 8, 'community', 'Day trip to support a coastal clean-up with a partner organisation.', 'Travel by bus to join a coastal clean-up. Lunch and transport provided. Limited seats.', 'Departure: Main Gate', CURDATE() + INTERVAL 16 DAY + INTERVAL '07:00' HOUR_MINUTE, CURDATE() + INTERVAL 16 DAY + INTERVAL '07:00' HOUR_MINUTE + INTERVAL 540 MINUTE, 45, 'rsvp', 'outreach@example.org', 'pending');
INSERT INTO event_interests VALUES (25, 'sustainability');
INSERT INTO event_interests VALUES (25, 'social-impact');

-- ---------------------------------------------------------------- demo users
-- The password hash below was made with PHP password_hash() (bcrypt).
INSERT INTO users (name, email, password_hash, role, organization_id, campus_id) VALUES
    ('Ama Mensah (demo student)', 'student@example.org', '$2y$10$xzw2xzmJrYFYdBQL2hL.GelkmD/a.e2RfqSBM1MhsIXU52FMAWSMi', 'student', NULL, 1),
    ('Kojo Asante (demo rep)', 'rep@example.org', '$2y$10$xzw2xzmJrYFYdBQL2hL.GelkmD/a.e2RfqSBM1MhsIXU52FMAWSMi', 'org_rep', 1, 1),
    ('Esi Owusu (demo admin)', 'admin@example.org', '$2y$10$xzw2xzmJrYFYdBQL2hL.GelkmD/a.e2RfqSBM1MhsIXU52FMAWSMi', 'admin', NULL, NULL),
    ('Kwame Darko (demo student)', 'riverside@example.org', '$2y$10$xzw2xzmJrYFYdBQL2hL.GelkmD/a.e2RfqSBM1MhsIXU52FMAWSMi', 'student', NULL, 2);

-- One rep for every other organization: rep2@example.org is the rep for organization 2, and so on.
-- Each rep's campus is their organization's campus (8, 9, 13, 14 and 15 are on Riverside).
INSERT INTO users (name, email, password_hash, role, organization_id, campus_id) VALUES
    ('Abena Boateng (demo rep)', 'rep2@example.org', '$2y$10$xzw2xzmJrYFYdBQL2hL.GelkmD/a.e2RfqSBM1MhsIXU52FMAWSMi', 'org_rep', 2, 1),
    ('Yaw Mensah (demo rep)', 'rep3@example.org', '$2y$10$xzw2xzmJrYFYdBQL2hL.GelkmD/a.e2RfqSBM1MhsIXU52FMAWSMi', 'org_rep', 3, 1),
    ('Efua Owusu (demo rep)', 'rep4@example.org', '$2y$10$xzw2xzmJrYFYdBQL2hL.GelkmD/a.e2RfqSBM1MhsIXU52FMAWSMi', 'org_rep', 4, 1),
    ('Kofi Addo (demo rep)', 'rep5@example.org', '$2y$10$xzw2xzmJrYFYdBQL2hL.GelkmD/a.e2RfqSBM1MhsIXU52FMAWSMi', 'org_rep', 5, 1),
    ('Akosua Badu (demo rep)', 'rep6@example.org', '$2y$10$xzw2xzmJrYFYdBQL2hL.GelkmD/a.e2RfqSBM1MhsIXU52FMAWSMi', 'org_rep', 6, 1),
    ('Kwabena Osei (demo rep)', 'rep7@example.org', '$2y$10$xzw2xzmJrYFYdBQL2hL.GelkmD/a.e2RfqSBM1MhsIXU52FMAWSMi', 'org_rep', 7, 1),
    ('Adwoa Asare (demo rep)', 'rep8@example.org', '$2y$10$xzw2xzmJrYFYdBQL2hL.GelkmD/a.e2RfqSBM1MhsIXU52FMAWSMi', 'org_rep', 8, 2),
    ('Kwesi Appiah (demo rep)', 'rep9@example.org', '$2y$10$xzw2xzmJrYFYdBQL2hL.GelkmD/a.e2RfqSBM1MhsIXU52FMAWSMi', 'org_rep', 9, 2),
    ('Afia Ofori (demo rep)', 'rep10@example.org', '$2y$10$xzw2xzmJrYFYdBQL2hL.GelkmD/a.e2RfqSBM1MhsIXU52FMAWSMi', 'org_rep', 10, 1),
    ('Nana Agyeman (demo rep)', 'rep11@example.org', '$2y$10$xzw2xzmJrYFYdBQL2hL.GelkmD/a.e2RfqSBM1MhsIXU52FMAWSMi', 'org_rep', 11, 1),
    ('Esi Quaye (demo rep)', 'rep12@example.org', '$2y$10$xzw2xzmJrYFYdBQL2hL.GelkmD/a.e2RfqSBM1MhsIXU52FMAWSMi', 'org_rep', 12, 1),
    ('Kojo Danso (demo rep)', 'rep13@example.org', '$2y$10$xzw2xzmJrYFYdBQL2hL.GelkmD/a.e2RfqSBM1MhsIXU52FMAWSMi', 'org_rep', 13, 2),
    ('Ama Sarpong (demo rep)', 'rep14@example.org', '$2y$10$xzw2xzmJrYFYdBQL2hL.GelkmD/a.e2RfqSBM1MhsIXU52FMAWSMi', 'org_rep', 14, 2),
    ('Yaa Nkansah (demo rep)', 'rep15@example.org', '$2y$10$xzw2xzmJrYFYdBQL2hL.GelkmD/a.e2RfqSBM1MhsIXU52FMAWSMi', 'org_rep', 15, 2),
    ('Fiifi Acquah (demo rep)', 'rep16@example.org', '$2y$10$xzw2xzmJrYFYdBQL2hL.GelkmD/a.e2RfqSBM1MhsIXU52FMAWSMi', 'org_rep', 16, 1);

-- One campus rep per campus: they approve the students who sign up for their campus.
INSERT INTO users (name, email, password_hash, role, organization_id, campus_id) VALUES
    ('Adjoa Mensah (demo campus rep)', 'campus@example.org', '$2y$10$xzw2xzmJrYFYdBQL2hL.GelkmD/a.e2RfqSBM1MhsIXU52FMAWSMi', 'campus_rep', NULL, 1),
    ('Kwaku Boadu (demo campus rep)', 'campus2@example.org', '$2y$10$xzw2xzmJrYFYdBQL2hL.GelkmD/a.e2RfqSBM1MhsIXU52FMAWSMi', 'campus_rep', NULL, 2);

-- A new student still waiting for the Hillside campus rep to approve their sign-up.
INSERT INTO users (name, email, password_hash, role, organization_id, campus_id, status) VALUES
    ('Selorm Agbeko (waiting for approval)', 'pending@example.org', '$2y$10$xzw2xzmJrYFYdBQL2hL.GelkmD/a.e2RfqSBM1MhsIXU52FMAWSMi', 'student', NULL, 1, 'pending');

-- ---------------------------------------------------------------- campuses & visibility
-- Every organization starts on Hillside Campus (id 1); these five are on Riverside.
UPDATE organizations SET campus_id = 2 WHERE id IN (8, 9, 13, 14, 15);
-- These events are only shown to students of the organizer's own campus.
UPDATE events SET visibility = 'campus' WHERE id IN (9, 13, 15);
