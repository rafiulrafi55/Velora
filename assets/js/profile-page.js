import { auth, db } from "./firebase-app.js";
import {
  onAuthStateChanged,
  updateProfile,
  updatePassword,
  reauthenticateWithCredential,
  EmailAuthProvider,
} from "https://www.gstatic.com/firebasejs/12.10.0/firebase-auth.js";
import {
  ref,
  get,
  set,
} from "https://www.gstatic.com/firebasejs/12.10.0/firebase-database.js";

const avatar = document.getElementById("profile-avatar");
const nameInput = document.getElementById("profile-name");
const emailInput = document.getElementById("profile-email");
const phoneInput = document.getElementById("profile-phone");
const countryInput = document.getElementById("profile-country");
const editBtn = document.getElementById("edit-btn");
const saveBtn = document.getElementById("save-btn");
const cancelBtn = document.getElementById("cancel-btn");
const form = document.getElementById("profile-form");
const changePasswordBtn = document.getElementById("change-password-btn");
const changeOverlay = document.getElementById("change-password-overlay");
const changeForm = document.getElementById("change-password-form");
const currentPwdInput = document.getElementById("current-password");
const newPwdInput = document.getElementById("new-password");
const confirmPwdInput = document.getElementById("confirm-password");
const cancelChangeBtn = document.getElementById("change-password-cancel");

let originalData = {};
let currentUser = null;

function setFieldsEditable(editable) {
  [nameInput, phoneInput, countryInput].forEach((input) => {
    input.readOnly = !editable;
    input.style.background = editable ? "#fff" : "#f1f5f9";
    input.style.border = editable ? "1px solid #cbd5e1" : "none";
  });
  editBtn.style.display = editable ? "none" : "inline-block";
  saveBtn.style.display = editable ? "inline-block" : "none";
  cancelBtn.style.display = editable ? "inline-block" : "none";
}

function fillProfileFields(data) {
  nameInput.value = data.name || "";
  emailInput.value = data.email || "";
  phoneInput.value = data.phone || "";
  countryInput.value = data.countryCode || "";
  if (data.avatarUrl) {
    avatar.src = data.avatarUrl;
  }
}

function fetchUserProfile(uid) {
  const userRef = ref(db, `users/${uid}`);
  get(userRef).then((snapshot) => {
    if (snapshot.exists()) {
      originalData = snapshot.val();
      fillProfileFields(originalData);
    }
  });
}

onAuthStateChanged(auth, (user) => {
  if (!user) {
    window.location.replace("signin.html");
    return;
  }
  currentUser = user;
  emailInput.value = user.email || "";
  if (user.photoURL) avatar.src = user.photoURL;
  fetchUserProfile(user.uid);
});

editBtn.addEventListener("click", () => {
  setFieldsEditable(true);
});

cancelBtn.addEventListener("click", () => {
  fillProfileFields(originalData);
  setFieldsEditable(false);
});

form.addEventListener("submit", async (e) => {
  e.preventDefault();
  if (!currentUser) return;
  const updated = {
    name: nameInput.value.trim(),
    email: emailInput.value.trim(),
    phone: phoneInput.value.trim(),
    countryCode: countryInput.value.trim(),
    avatarUrl: avatar.src,
  };
  // Save to database
  await set(ref(db, `users/${currentUser.uid}`), updated);
  // Optionally update Firebase Auth profile
  await updateProfile(currentUser, { displayName: updated.name });
  originalData = { ...updated };
  setFieldsEditable(false);
});

// Change password flow
changePasswordBtn.addEventListener("click", () => {
  changeOverlay.style.display = "flex";
  setTimeout(() => currentPwdInput.focus(), 50);
});

cancelChangeBtn.addEventListener("click", () => {
  changeOverlay.style.display = "none";
  changeForm.reset();
});

changeForm.addEventListener("submit", async (e) => {
  e.preventDefault();
  if (!currentUser) return;
  const currentPwd = currentPwdInput.value.trim();
  const newPwd = newPwdInput.value.trim();
  const confirmPwd = confirmPwdInput.value.trim();
  if (newPwd.length < 6) {
    alert("New password must be at least 6 characters.");
    return;
  }
  if (newPwd !== confirmPwd) {
    alert("New passwords do not match.");
    return;
  }
  try {
    const cred = EmailAuthProvider.credential(currentUser.email, currentPwd);
    await reauthenticateWithCredential(currentUser, cred);
    await updatePassword(currentUser, newPwd);
    changeOverlay.style.display = "none";
    changeForm.reset();
    alert("Password changed successfully.");
  } catch (err) {
    console.error(err);
    alert(err.message || "Failed to change password.");
  }
});

setFieldsEditable(false);
