// Custom confirmation modal for consistent UI
export function showConfirmModal(message, options = {}) {
  return new Promise((resolve) => {
    // Remove any existing modal
    document
      .querySelectorAll(".confirm-modal-overlay")
      .forEach((e) => e.remove());

    const overlay = document.createElement("div");
    overlay.className = "confirm-modal-overlay";

    const modal = document.createElement("div");
    modal.className = "confirm-modal";

    const msgElem = document.createElement("h3");
    msgElem.textContent = message;
    modal.appendChild(msgElem);

    const btns = document.createElement("div");
    btns.className = "confirm-modal-btns";

    const okBtn = document.createElement("button");
    okBtn.className = "confirm";
    okBtn.textContent = options.okText || "Yes";
    okBtn.onclick = () => {
      overlay.remove();
      resolve(true);
    };

    const cancelBtn = document.createElement("button");
    cancelBtn.className = "cancel";
    cancelBtn.textContent = options.cancelText || "Cancel";
    cancelBtn.onclick = () => {
      overlay.remove();
      resolve(false);
    };

    btns.appendChild(okBtn);
    btns.appendChild(cancelBtn);
    modal.appendChild(btns);
    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    setTimeout(() => okBtn.focus(), 100);
    overlay.addEventListener("click", (e) => {
      if (e.target === overlay) {
        overlay.remove();
        resolve(false);
      }
    });
    document.addEventListener("keydown", function escHandler(e) {
      if (e.key === "Escape") {
        overlay.remove();
        resolve(false);
        document.removeEventListener("keydown", escHandler);
      }
    });
  });
}
