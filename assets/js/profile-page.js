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
import { deleteUser } from "https://www.gstatic.com/firebasejs/12.10.0/firebase-auth.js";
import {
  query,
  orderByChild,
  equalTo,
  remove,
} from "https://www.gstatic.com/firebasejs/12.10.0/firebase-database.js";
import { showConfirmModal } from "./confirm-modal.js";

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
const deleteAccountBtn = document.getElementById("delete-account-btn");
const deleteOverlay = document.getElementById("delete-account-overlay");
const deletePwdInput = document.getElementById("delete-account-password");
const deleteConfirmBtn = document.getElementById("delete-account-confirm");
const deleteCancelBtn = document.getElementById("delete-account-cancel");

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

// Delete account flow: reauthenticate, remove DB data, then delete Auth user
if (deleteAccountBtn) {
  deleteAccountBtn.addEventListener("click", async () => {
    if (!currentUser) return;
    const confirmed = await showConfirmModal(
      "Permanently delete your account and all associated data? This cannot be undone.",
      { okText: "Confirm", cancelText: "Cancel" },
    );
    if (!confirmed) return;

    // show password input modal instead of prompt
    async function showDeletePasswordModal() {
      return new Promise((resolve) => {
        // Re-query or reattach the overlay in case showConfirmModal removed it
        if (!deleteOverlay) {
          try {
            deleteOverlay = document.getElementById("delete-account-overlay");
          } catch (e) {}
        }
        if (deleteOverlay && !document.body.contains(deleteOverlay)) {
          try {
            document.body.appendChild(deleteOverlay);
          } catch (e) {
            console.error("Failed to reattach deleteOverlay", e);
          }
        }
        console.log(
          "showDeletePasswordModal: overlay, confirmBtn, cancelBtn:",
          { deleteOverlay, deleteConfirmBtn, deleteCancelBtn },
        );
        if (!deleteOverlay) return resolve(null);
        // make visible
        deleteOverlay.style.display = "flex";
        if (deletePwdInput) deletePwdInput.value = "";
        setTimeout(() => deletePwdInput && deletePwdInput.focus(), 50);

        let cleanup = function () {
          try {
            deleteConfirmBtn &&
              deleteConfirmBtn.removeEventListener("click", onConfirm);
          } catch (e) {}
          try {
            deleteCancelBtn &&
              deleteCancelBtn.removeEventListener("click", onCancel);
          } catch (e) {}
          try {
            deleteOverlay &&
              deleteOverlay.removeEventListener("click", onOverlayClick);
          } catch (e) {}
        };

        function onConfirm() {
          try {
            if (onConfirm._running) return;
            onConfirm._running = true;
            console.log("delete modal confirm clicked");
            const v = deletePwdInput ? deletePwdInput.value.trim() : "";
            deleteOverlay.style.display = "none";
            cleanup();
            resolve(v || "");
          } catch (e) {
            console.error("onConfirm error", e);
            cleanup();
            resolve(null);
          }
        }

        function onCancel() {
          try {
            deleteOverlay.style.display = "none";
          } catch (e) {}
          cleanup();
          resolve(null);
        }

        function onOverlayClick(e) {
          if (e.target === deleteOverlay) onCancel();
        }

        // Attach click handlers
        if (deleteConfirmBtn) {
          deleteConfirmBtn.addEventListener("click", onConfirm);
          // also listen for pointerdown to handle some mobile browsers where click may not fire
          deleteConfirmBtn.addEventListener(
            "pointerdown",
            (e) => {
              try {
                e.preventDefault();
                onConfirm();
              } catch (err) {
                console.error("pointerdown handler error", err);
              }
            },
            { passive: false },
          );
        }
        if (deleteCancelBtn)
          deleteCancelBtn.addEventListener("click", onCancel);
        if (deleteOverlay)
          deleteOverlay.addEventListener("click", onOverlayClick);
        console.log("delete modal listeners attached", {
          confirm: !!deleteConfirmBtn,
          cancel: !!deleteCancelBtn,
          overlay: !!deleteOverlay,
          confirmDisabled: deleteConfirmBtn
            ? !!deleteConfirmBtn.disabled
            : null,
        });

        // temporary: capture all clicks while modal open to help debug missing clicks
        function docCapture(e) {
          try {
            if (!deleteOverlay || deleteOverlay.style.display !== "flex")
              return;
            console.log("docCapture click target:", e.target);
          } catch (err) {
            console.error("docCapture error", err);
          }
        }
        document.addEventListener("click", docCapture, true);
        const oldCleanup2 = cleanup;
        cleanup = function () {
          try {
            document.removeEventListener("click", docCapture, true);
          } catch (e) {}
          oldCleanup2();
        };

        // allow Enter/Escape keys inside the password input
        const keyHandler = (e) => {
          if (e.key === "Enter") {
            e.preventDefault();
            onConfirm();
          }
          if (e.key === "Escape") {
            e.preventDefault();
            onCancel();
          }
        };
        deletePwdInput &&
          deletePwdInput.addEventListener("keydown", keyHandler);

        // ensure keyHandler is removed on cleanup
        const oldCleanup = cleanup;
        cleanup = function () {
          try {
            deletePwdInput &&
              deletePwdInput.removeEventListener("keydown", keyHandler);
          } catch (e) {}
          oldCleanup();
        };

        // safety timeout: auto-cancel after 5 minutes
        const timeoutId = setTimeout(
          () => {
            console.warn("delete password modal auto-cancel timeout");
            onCancel();
          },
          5 * 60 * 1000,
        );
        // wrap resolve so timeout is cleared
        const origResolve = resolve;
        resolve = (v) => {
          clearTimeout(timeoutId);
          origResolve(v);
        };
      });
    }

    const pwd = await showDeletePasswordModal();
    console.log(
      "delete flow: password collected?",
      typeof pwd,
      pwd ? "(present)" : "(empty or cancelled)",
    );
    if (pwd === null) return; // user cancelled
    if (!pwd) {
      alert("Password required to delete account.");
      return;
    }

    try {
      const cred = EmailAuthProvider.credential(currentUser.email, pwd);
      console.log("Reauthenticating user before deletion...");
      await reauthenticateWithCredential(currentUser, cred);
      console.log(
        "Reauthentication successful; proceeding to delete DB nodes...",
      );

      // delete user's events and related nodes
      const evQ = query(
        ref(db, "events"),
        orderByChild("createdBy"),
        equalTo(currentUser.uid),
      );
      const evSnap = await get(evQ);
      const eventIds = [];
      if (evSnap.exists()) evSnap.forEach((s) => eventIds.push(s.key));

      const deletes = [];
      for (const id of eventIds) {
        deletes.push(remove(ref(db, `events/${id}`)));

        const guestQ = query(
          ref(db, "guests"),
          orderByChild("eventId"),
          equalTo(id),
        );
        const guestSnap = await get(guestQ);
        if (guestSnap.exists())
          guestSnap.forEach((g) =>
            deletes.push(remove(ref(db, `guests/${g.key}`))),
          );

        const checkQ = query(
          ref(db, "checkins"),
          orderByChild("eventId"),
          equalTo(id),
        );
        const checkSnap = await get(checkQ);
        if (checkSnap.exists())
          checkSnap.forEach((c) =>
            deletes.push(remove(ref(db, `checkins/${c.key}`))),
          );

        const actQ = query(
          ref(db, "activity"),
          orderByChild("eventId"),
          equalTo(id),
        );
        const actSnap = await get(actQ);
        if (actSnap.exists())
          actSnap.forEach((a) =>
            deletes.push(remove(ref(db, `activity/${a.key}`))),
          );
      }

      // remove user profile
      deletes.push(remove(ref(db, `users/${currentUser.uid}`)));

      await Promise.all(deletes);

      // delete auth account
      await deleteUser(currentUser);
      alert("Account and data deleted successfully.");
      window.location.replace("index.html");
    } catch (err) {
      console.error("Account deletion failed", err);
      alert(err.message || "Failed to delete account.");
    }
  });
}
