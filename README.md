# <p align="center">🌍 TravelScope — Your Global Adventure Partner</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.x-777BB4?style=for-the-badge&logo=php&logoColor=white" />
  <img src="https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge&logo=mysql&logoColor=white" />
  <img src="https://img.shields.io/badge/Vanilla_JS-ES6+-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black" />
  <img src="https://img.shields.io/badge/CSS3-Glassmorphism-1572B6?style=for-the-badge&logo=css3&logoColor=white" />
</p>

---

## 👋 Hey there, Fellow Dev!

Welcome to **TravelScope**! This is a project I built to take the guesswork out of planning a trip. It’s not just a search bar; it’s a full-on data engine that pulls in real prices, weather patterns, and tourist spots to give you a complete "vibe check" of any city on Earth.

I built this with a **Premium Glassmorphism UI** because I believe tools should look as good as they work. Let's dive in! 🚀

---

## ✨ What's Inside?

> [!TIP]
> **Check out the Search History!** 
> It stays synced to your account so you can compare multiple destinations at once.

### 🌟 Core Features
| Feature | Description | Status |
| :--- | :--- | :---: |
| **Auth System** | Secure Login/Signup with hashed passwords. | ✅ |
| **Budget Engine** | Real-time hotel & food costs from Amadeus/Teleport. | ✅ |
| **Weather API** | Current weather + 5-day forecasts. | ✅ |
| **Sightseeing** | Automated landmark discovery via Wikipedia imagery. | ✅ |
| **Search History** | Personalized, cloud-synced exploration logs. | ✅ |

---

## 🛠️ The "Under the Hood" Stuff

This is how all the pieces fit together:

- **Frontend**: I used raw CSS variables for a custom design system. No heavy frameworks—just clean, fast, and responsive code.
- **The Brain (PHP)**: It handles the heavy lifting, like communicating with 4 different APIs simultaneously and keeping your sessions secure.
- **The Memory (MySQL)**: All your data is stored in organized relational tables for quick history lookups.

---

## 🚀 Speedrun Setup

Getting this running on your local machine is a breeze!

### 1. Configure the "Secret Sauce"
Open `config.php` and plug in your keys. It looks like this:
```php
// Your Database
define('DB_HOST', 'sql306.infinityfree.com');
define('DB_NAME', 'my_travel_db');

// Your API Keys
define('OWM_API_KEY', 'xxx_your_key_xxx');
define('GEO_API_KEY', 'xxx_your_key_xxx');
```

### 2. The Auto-Builder
Don't worry about manual SQL imports! Just visit this link once:
👉 `http://your-site.com/setup_db.php`

> [!IMPORTANT]
> If you ever see a "Column not found" error, I've got your back! Just run **`fix_db.php`** and it will automatically repair your database tables to match the newest code.

---

## 💡 Lessons from the Journey

Building this was a blast! I learned how to:
- 🔗 **Chaining APIs**: How to fetch data from one API to feed another.
- 🍪 **Cookie Magic**: Handling PHP sessions correctly for AJAX requests.
- 🎨 **Modern CSS**: Using `backdrop-filter` and `radial-gradients` for that premium look.

---

## 📬 Let's Connect!

If you're using this for a project or just want to chat about the code, feel free to reach out! Let's build something awesome together. ✈️

<p align="center">
  <sub>Built with ❤️ by a Developer, for Developers.</sub>
</p>
