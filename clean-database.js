// clean-database.js
// Script to clean all user data from Firebase Realtime Database but keep structure
// Usage: Import and call cleanDatabase() from your admin panel or dev console

import { db } from "./assets/js/firebase-app.js";
import {
  ref,
  set,
} from "https://www.gstatic.com/firebasejs/12.10.0/firebase-database.js";

export async function cleanDatabase() {
  try {
    await Promise.all([
      set(ref(db, "events"), {}),
      set(ref(db, "guests"), {}),
      set(ref(db, "checkins"), {}),
      set(ref(db, "activity"), {}),
      // Add more collections if needed
    ]);
    alert("All user data has been deleted, but structure is preserved.");
  } catch (err) {
    alert("Failed to clean database. See console for details.");
    console.error(err);
  }
}

// To run: import { cleanDatabase } from './clean-database.js'; cleanDatabase();
