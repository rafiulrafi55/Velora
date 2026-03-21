# WGMS (Web-based Guest Management System)

[![On Development](https://img.shields.io/badge/status-on--development-blueviolet)](#)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![Firebase](https://img.shields.io/badge/Backend-Firebase-orange)](https://firebase.google.com/)
[![HTML5](https://img.shields.io/badge/Frontend-HTML5-blue)](https://developer.mozilla.org/en-US/docs/Web/Guide/HTML/HTML5)
[![JavaScript](https://img.shields.io/badge/JavaScript-ES6+-f7df1e?logo=javascript&logoColor=black)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)
[![Status: Active](https://img.shields.io/badge/status-active-brightgreen)](#)

---

## Overview

Velora is a web-based guest management system designed for event organizers to manage events, guests, check-ins, and user profiles securely and efficiently. Built with vanilla HTML, CSS, and JavaScript, and powered by Firebase Authentication and Realtime Database, WGMS provides a modern, mobile-friendly interface for both users and administrators.

---

## Features

- **User Authentication**: Secure sign-up, sign-in, password reset, and account deletion using Firebase Auth.
- **Per-User Data Isolation**: Each user can only view and manage their own events, guests, and check-ins.
- **Event Management**: Create, view, and delete events scoped to the signed-in user.
- **Guest Management**: Add, view, and delete guests for each event. Batch operations are scoped to user-owned events.
- **Check-in Tracking**: Track guest check-ins for events.
- **Profile Management**: Update profile details, change password, and delete account with secure reauthentication.
- **Admin Role**: Optional admin registration with key-based access.
- **Mobile-First Design**: Responsive UI with mobile-specific enhancements (e.g., cookie banner gap).
- **Security**: Firebase Realtime Database rules enforce per-user data access and index requirements.
- **Loading Overlays & Modals**: User-friendly feedback for destructive actions and loading states.

---

## Project Structure

```
WGMS/
├── about-mobile.html
├── clean-database.js
├── contact-mobile.html
├── create-event.html
├── dashboard.html
├── firebase-config.html
├── guests.html
├── index.html
├── LICENSE
├── manage-events.html
├── privacy-policy.html
├── profile.html
├── readme.md
├── signin.html
├── signup.html
├── assets/
│   ├── css/
│   │   ├── confirm-modal.css
│   │   └── profile-cards.css
│   ├── img/
│   └── js/
│       ├── confirm-modal.js
│       ├── firebase-app.js
│       ├── github-profile.js
│       └── profile-page.js
└── Work-logs/
    ├── WORK_LOG_2026-03-13.txt
    ├── WORK_LOG_2026-03-14.txt
    └── WORK_LOG_2026-03-15_to_2026-03-19.txt
```

---

## Setup & Deployment

### 1. Clone the Repository

```sh
git clone <repo-url>
cd WGMS
```

### 2. Firebase Setup

- Create a Firebase project at [Firebase Console](https://console.firebase.google.com/).
- Enable **Authentication** (Email/Password) and **Realtime Database**.
- Copy your Firebase config to `assets/js/firebase-app.js` (see `firebase-config.html` for guidance).
- Deploy the provided `database.rules.json` to your Firebase project:

```sh
npm install -g firebase-tools
firebase login
firebase deploy --only database --project <your-project-id>
```

### 3. Local Development

- Open `index.html` or any page in a local web server (e.g., [Live Server](https://marketplace.visualstudio.com/items?itemName=ritwickdey.LiveServer) for VS Code).
- For full functionality, serve over `http://localhost` or deploy to a static host (Firebase Hosting, Netlify, Vercel, etc.).

---

## Usage

- **Sign Up**: Register a new account via `signup.html`.
- **Sign In**: Log in via `signin.html`.
- **Dashboard**: Manage your events and guests in `dashboard.html` and `guests.html`.
- **Profile**: Update your profile, change password, or delete your account in `profile.html`.
- **Admin**: Register as admin with a valid key (if enabled).

---

## Security & Data Isolation

- All data reads/writes are scoped to the authenticated user via client logic and Firebase Realtime Database rules.
- Database rules enforce `.indexOn` for `createdBy` and `eventId` to support efficient queries and prevent permission errors.
- Sensitive actions (password change, account deletion) require reauthentication.

---

## Customization

- **UI**: Modify styles in `assets/css/`.
- **Logic**: Update or extend features in `assets/js/`.
- **Database Rules**: Edit `database.rules.json` and redeploy as needed.

---

## Contributing

Pull requests are welcome! Please open an issue to discuss major changes or feature requests.

---

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

---

## Acknowledgments

- [Firebase](https://firebase.google.com/) for backend services
- [Shields.io](https://shields.io/) for badges
- [MDN Web Docs](https://developer.mozilla.org/) for HTML/CSS/JS references

---

## Work Logs

See the `Work-logs/` directory for detailed daily progress and change history.
