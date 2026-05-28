<?php
session_start();
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Willy Satrya - CV/Resume</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Navigation */
        .nav {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .nav-toggle {
            background: rgba(255,255,255,0.9);
            border: none;
            padding: 12px;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .nav-toggle:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }

        .nav-menu {
            position: absolute;
            top: 60px;
            right: 0;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }

        .nav-menu.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .nav-menu a {
            display: block;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .nav-menu a:hover {
            background: #667eea;
            color: white;
            transform: translateX(10px);
        }

        /* Hero Section */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.4);
            z-index: 1;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 800px;
            animation: fadeInUp 1s ease;
        }

        .profile-photo {
            margin-bottom: 25px; /* Jarak foto ke nama Bapak */
            display: flex;
            justify-content: center;
        }

        .profile-photo img {
            width: 160px; /* Ukuran bisa Bapak sesuaikan */
            height: 160px;
            border-radius: 50%; /* Membuat lingkaran */
            object-fit: cover; /* Biar foto tidak gepeng */
            border: 4px solid rgba(255, 255, 255, 0.8); /* Frame putih tipis */
            box-shadow: 0 8px 20px rgba(0,0,0,0.3); /* Efek bayangan */
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hero h1 {
            font-size: 4rem;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .hero .subtitle {
            font-size: 1.5rem;
            margin-bottom: 30px;
            opacity: 0.9;
        }

        .download-btn {
            display: inline-block;
            background: #ff6b6b;
            color: white;
            padding: 15px 40px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(255,107,107,0.4);
        }

        .download-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(255,107,107,0.6);
        }

        /* Sections */
        .section {
            padding: 100px 0;
            max-width: 1000px;
            margin: 0 auto;
        }

        .section h2 {
            font-size: 3rem;
            text-align: center;
            margin-bottom: 60px;
            color: white;
            position: relative;
        }

        .section h2::after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: #ff6b6b;
            border-radius: 2px;
        }

        /* About */
        .about-content {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            padding: 50px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            text-align: center;
        }

        .about-content p {
            font-size: 1.2rem;
            margin-bottom: 30px;
            color: #555;
        }

        /* Skills */
        .skills-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }

        .skill-item {
            background: rgba(255,255,255,0.95);
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            opacity: 0;
            transform: translateY(30px);
        }

        .skill-item.animate {
            opacity: 1;
            transform: translateY(0);
        }

        .skill-item:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
        }

        .skill-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            color: #667eea;
        }

        .skill-item h3 {
            margin-bottom: 15px;
            color: #333;
        }

        /* Experience */
        .timeline {
            position: relative;
            max-width: 800px;
            margin: 0 auto;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            width: 4px;
            height: 100%;
            background: rgba(255,255,255,0.3);
        }

        .timeline-item {
            margin-bottom: 60px;
            position: relative;
            opacity: 0;
            transform: translateX(-50px);
        }

        .timeline-item.animate {
            opacity: 1;
            transform: translateX(0);
        }

        .timeline-item:nth-child(even) {
            transform: translateX(50px);
        }

        .timeline-item:nth-child(even).animate {
            transform: translateX(0);
        }

        .timeline-content {
            background: rgba(255,255,255,0.95);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            position: relative;
        }

        .timeline-date {
            color: #ff6b6b;
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 10px;
        }

        .timeline-title {
            font-size: 1.4rem;
            margin-bottom: 15px;
            color: #333;
        }

        /* Contact */
        .contact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
        }

        .contact-item {
            background: rgba(255,255,255,0.95);
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
        }

        .contact-item:hover {
            transform: translateY(-10px);
            text-decoration: none;
            color: inherit;
        }

        .contact-icon {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 20px;
            text-decoration: none;
            color: inherit;
        }

        .contact-card {
            text-decoration: none !important; /* Hilangkan garis bawah */
            color: #333 !important; /* Pakai warna teks gelap biar jelas */
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            padding: 20px;
        }

        /* Pastikan tidak ada garis bawah saat kursor di atas kotak */
        .contact-card:hover, .contact-card:active {
            text-decoration: none !important;
            color: #667eea !important; /* Berubah warna dikit pas di-hover biar keren */
        }
        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .section {
                padding: 60px 20px;
            }
            
            .section h2 {
                font-size: 2rem;
            }
            
            .timeline::before {
                left: 20px;
            }
            
            .timeline-item,
            .timeline-item:nth-child(even) {
                transform: translateX(0);
                margin-left: 50px;
            }
            
            .timeline-item.animate,
            .timeline-item:nth-child(even).animate {
                transform: translateX(0);
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="nav">
        <button class="nav-toggle" onclick="toggleNav()">
            <i class="fas fa-bars"></i>
        </button>
        <div class="nav-menu" id="navMenu">
            <a href="#home"><i class="fas fa-home"></i> Home</a>
            <a href="#about"><i class="fas fa-user"></i> About</a>
            <a href="#skills"><i class="fas fa-code"></i> Skills</a>
            <a href="#experience"><i class="fas fa-briefcase"></i> Experience</a>
            <a href="#contact"><i class="fas fa-envelope"></i> Contact</a>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="hero-content">
            <div class="profile-photo">
            <img src="foto-bapak.jpg" alt="Willy Alga Satrya">
            </div>
            <h1>Willy Alga Satrya</h1>
            <p class="subtitle">Network & Cloud Computing Educator</p>
            <a href="#" class="download-btn" onclick="downloadCV()">
                <i class="fas fa-download"></i> Download CV
            </a>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="section">
        <h2>About Me</h2>
        <div class="about-content">
            <p>
                Passionate Network and Cloud Computing with 5+ years of experience teaching
                Mikrotik network setup, Cisco packet tracer, PC Hardware instalation and repair, web server, website and database management.
                I specialize in networking, server setup, and teaching website technologies.
            </p>
            <p>
                When I'm not coding, you can find me exploring new technologies, 
                contributing to open source projects, or enjoying a good cup of coffee.


                <p>Malang, Indonesia <i class="fas fa-map-marker-alt"></i> </p>
                <i class="fas fa-graduation-cap"></i>
                <p>Bachelor of Informatic Engineering Education in The State University of Malang </p>
            </p>
        </div>
    </section>

    <!-- Skills Section -->
    <section id="skills" class="section">
        <h2>Skills</h2>
        <div class="skills-grid">
            <div class="skill-item">
                <div class="skill-icon">
                    <i class="fas fa-network-wired"></i>
                </div>
                <h3>Network</h3>
                <p>Advanced proficiency in network setup</p>
            </div>
            <div class="skill-item">
                <div class="skill-icon">
                    <i class="fas fa-server"></i>
                </div>
                <h3>Server</h3>
                <p>Nginx, Cloudflared, SSH server and VOIP server (asterisk)</p>
            </div>
            <div class="skill-item">
                <div class="skill-icon">
                    <i class="fas fa-microchip"></i>
                </div>
                <h3>Hardware and Software</h3>
                <p>Install and repair PC hardware and software</p>
            </div>
            <div class="skill-item">
                <div class="skill-icon">
                    <i class="fas fa-database"></i>
                </div>
                <h3>Databases</h3>
                <p>MySql, MariaDb</p>
            </div>
        </div>
    </section>


    <!-- Experience Section -->
    <section id="experience" class="section">
        <h2>Experience</h2>
        <div class="timeline">
            <div class="timeline-item">
                <div class="timeline-content">
                    <div class="timeline-date">February 2015-March 2015</div>
                    <h3 class="timeline-title">Junior Developer</h3>
                    <p>Pt. Data Integra Dinamika</p>
                    <p>Learning basic programming for military vehicles</p>
                </div>
            </div>
            <div class="timeline-item">
                <div class="timeline-content">
                    <div class="timeline-date">2015 - Present</div>
                    <h3 class="timeline-title">Network and Cloud computing educator</h3>
                    <p>SMKN 11 Malang.</p>
                    <p>Teaching students from basic network, advance network and server configuration.</p>
                </div>
            </div>
            <div class="timeline-item">
                <div class="timeline-content">
                    <div class="timeline-date">2015 - Present</div>
                    <h3 class="timeline-title">Head of Computer Lab</h3>
                    <p>SMKN 11 Malang.</p>
                    <p>Managing computer hardware, software and network</p>
                </div>
            </div>
        </div>
    </section>

    <section id="contact" class="section">
    <h2>Get In Touch</h2>
    <div class="contact-grid">
        <div class="contact-item">
            <a href="mailto:willygerrard@gmail.com" class="contact-card">
                <div class="contact-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <h3>Email</h3>
                <p>willygerrard@gmail.com</p>
            </a>
        </div>

        <div class="contact-item">
            <a href="https://github.com/willygerrard" target="_blank" class="contact-card">
                <div class="contact-icon">
                    <i class="fab fa-github"></i>
                </div>
                <h3>GitHub</h3>
                <p>github.com/willygerrard</p>
            </a>
        </div>

        <div class="contact-item">
            <a href="https://wa.me/6285649394728" target="_blank" class="contact-card">
                <div class="contact-icon">
                    <i class="fab fa-whatsapp"></i>
                </div>
                <h3>Phone</h3>
                <p>+62 85649394728</p>
            </a>
        </div>
    </div>
</section>

    <script>
        // Navigation toggle
        function toggleNav() {
            const navMenu = document.getElementById('navMenu');
            navMenu.classList.toggle('active');
        }

        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
                document.getElementById('navMenu').classList.remove('active');
            });
        });

        // Download CV function
        function downloadCV() {
            // Create a simple text file for demo
            const cvContent = `
Willy Alga Satrya - CV
=============

Experience:
- Junior Developer, PT Data Integra Dinamika. (2015)
- Network and Cloud computing educator, SMKN 11 Malang. (2015-present)
- Head of computer lab, SMKN 11 Malang. (2015-present)

Skills:
- Advanced proficiency in network setup
- Nginx, Cloudflared, SSH server and VOIP server (asterisk)
- Install and repair PC hardware and software
- MariaDb, MySql
            `;
            
            const blob = new Blob([cvContent], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'Willy_Satrya.txt';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        // Intersection Observer for animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate');
                }
            });
        }, observerOptions);

        // Observe skill items
        document.querySelectorAll('.skill-item').forEach(item => {
            observer.observe(item);
        });

        // Observe timeline items
        document.querySelectorAll('.timeline-item').forEach(item => {
            observer.observe(item);
        });

        // Navbar scroll effect
        window.addEventListener('scroll', () => {
            const navToggle = document.querySelector('.nav-toggle');
            if (window.scrollY > 100) {
                navToggle.style.background = 'rgba(255,107,107,0.95)';
            } else {
                navToggle.style.background = 'rgba(255,255,255,0.9)';
            }
        });

        // Close nav on outside click
        document.addEventListener('click', (e) => {
            const nav = document.querySelector('.nav');
            const navMenu = document.getElementById('navMenu');
            if (!nav.contains(e.target)) {
                navMenu.classList.remove('active');
            }
        });
    </script>
</body>
</html>
