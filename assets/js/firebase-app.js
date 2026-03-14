// Firebase App and Auth logic for GitHub Pages static deployment
import { initializeApp } from "https://www.gstatic.com/firebasejs/10.8.1/firebase-app.js";
import { getAuth } from "https://www.gstatic.com/firebasejs/10.8.1/firebase-auth.js";
import { getDatabase } from "https://www.gstatic.com/firebasejs/10.8.1/firebase-database.js";

const firebaseConfig = {
  apiKey: "AIzaSyCqIbdZ1u8VilV9XIBLnayEVCcZeFaG5bM",
  authDomain: "velora-ff18b.firebaseapp.com",
  databaseURL:
    "https://velora-ff18b-default-rtdb.asia-southeast1.firebasedatabase.app",
  projectId: "velora-ff18b",
  storageBucket: "velora-ff18b.firebasestorage.app",
  messagingSenderId: "265381777586",
  appId: "1:265381777586:web:a5fd4428ba77da3822b9ab",
};

// Initialize Firebase
export const app = initializeApp(firebaseConfig);
export const auth = getAuth(app);
export const db = getDatabase(app);
