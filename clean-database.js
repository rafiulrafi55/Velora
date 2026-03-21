import { db } from "./assets/js/firebase-app.js";
import {
  ref,
  remove,
} from "https://www.gstatic.com/firebasejs/12.10.0/firebase-database.js";

export async function cleanDatabase() {
  try {
    await Promise.all([
      remove(ref(db, "events")),
      remove(ref(db, "guests")),
      remove(ref(db, "checkins")),
      remove(ref(db, "activity")),
    ]);
  } catch (err) {
    alert("Failed to clean database. See console for details.");
    console.error(err);
  }
}
