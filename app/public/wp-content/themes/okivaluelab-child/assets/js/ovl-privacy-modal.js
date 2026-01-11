(() => {
  const settings = window.ovlPrivacyModalData || {};
  const triggerSelector = ".ovl-privacy-modal-trigger";
  const consentCheckboxSelector = "#ovl-privacy-consent, input[name='ovl_privacy_consent']";
  const registerButtonSelector = "#ovl-register-btn, #wpmem_register_form input[type='submit'].buttons, #wpmem_register_form input[type='submit']";
  const registerErrorSelector = "#ovl-register-error, .ovl-register-error";

  const createModal = () => {
    const overlay = document.createElement("div");
    overlay.className = "ovl-modal__overlay";
    overlay.hidden = true;

    const modal = document.createElement("div");
    modal.className = "ovl-modal";
    modal.setAttribute("role", "dialog");
    modal.setAttribute("aria-modal", "true");
    modal.setAttribute("aria-label", "プライバシーポリシー");
    modal.hidden = true;

    modal.innerHTML = `
      <div class="ovl-modal__header">
        <div class="ovl-modal__title"></div>
        <button type="button" class="ovl-modal__close" aria-label="閉じる">×</button>
      </div>
      <div class="ovl-modal__body"></div>
    `;

    document.body.appendChild(overlay);
    document.body.appendChild(modal);

    const titleEl = modal.querySelector(".ovl-modal__title");
    const bodyEl = modal.querySelector(".ovl-modal__body");
    const closeBtn = modal.querySelector(".ovl-modal__close");

    let lastActiveElement = null;

    const close = () => {
      overlay.hidden = true;
      modal.hidden = true;
      document.body.classList.remove("ovl-modal-open");
      if (lastActiveElement && typeof lastActiveElement.focus === "function") {
        lastActiveElement.focus();
      }
    };

    const open = async () => {
      lastActiveElement = document.activeElement;
      overlay.hidden = false;
      modal.hidden = false;
      document.body.classList.add("ovl-modal-open");

      titleEl.textContent = "プライバシーポリシー";
      bodyEl.innerHTML = '<p class="ovl-modal__loading">読み込み中…</p>';

      try {
        const html = await loadPrivacyHtml();
        bodyEl.innerHTML = html || '<p class="ovl-modal__error">読み込みに失敗しました。</p>';
      } catch (e) {
        bodyEl.innerHTML = '<p class="ovl-modal__error">読み込みに失敗しました。</p>';
      }

      closeBtn.focus();
    };

    overlay.addEventListener("click", close);
    closeBtn.addEventListener("click", close);
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && !modal.hidden) {
        event.preventDefault();
        close();
      }
    });

    return { openPrivacy: open, close };
  };

  const loadPrivacyHtml = async () => {
    if (settings.restUrl) {
      const res = await fetch(settings.restUrl, { credentials: "same-origin" });
      if (!res.ok) throw new Error("rest fetch failed");
      const data = await res.json();
      const title = data?.title?.rendered;
      const content = data?.content?.rendered;
      if (title) {
        const titleEl = document.querySelector(".ovl-modal__title");
        if (titleEl) titleEl.innerHTML = title;
      }
      return content || "";
    }

    if (!settings.privacyUrl) return "";

    const res = await fetch(settings.privacyUrl, { credentials: "same-origin" });
    if (!res.ok) throw new Error("url fetch failed");
    const text = await res.text();

    const doc = new DOMParser().parseFromString(text, "text/html");
    const main = doc.querySelector("main") || doc.querySelector(".wp-site-blocks") || doc.body;
    return main ? main.innerHTML : "";
  };

  const init = () => {
    const trigger = document.querySelector(triggerSelector);
    const modal = createModal();

    if (trigger) {
      document.addEventListener("click", (event) => {
        const link = event.target.closest(triggerSelector);
        if (!link) return;
        event.preventDefault();
        modal.openPrivacy();
      });
    }

    const consentCheckbox = document.querySelector(consentCheckboxSelector);
    const registerButton = document.querySelector(registerButtonSelector);
    let registerError = document.querySelector(registerErrorSelector);
    const registerForm =
      registerButton?.closest("form") || document.querySelector("#wpmem_register_form");
    if (!consentCheckbox || !registerButton || !registerForm) return;

    if (!registerError) {
      registerError = document.createElement("div");
      registerError.id = "ovl-register-error";
      registerError.className = "ovl-register-error";
      registerError.setAttribute("role", "alert");
      registerError.hidden = true;
      registerError.textContent = "プライバシーの同意にチェックをお願いします。";
      registerButton.insertAdjacentElement("afterend", registerError);
    }

    const showRegisterError = () => {
      registerError.hidden = false;
      registerError.style.display = "block";
      registerError.scrollIntoView({ block: "nearest" });
    };

    const hideRegisterError = () => {
      registerError.hidden = true;
    };

    consentCheckbox.addEventListener("change", () => {
      if (consentCheckbox.checked) {
        hideRegisterError();
      }
    });

    registerButton.addEventListener("click", (event) => {
      if (consentCheckbox.checked) return;
      event.preventDefault();
      showRegisterError();
      consentCheckbox.focus();
    });

    registerForm.addEventListener("submit", (event) => {
      if (consentCheckbox.checked) return;
      event.preventDefault();
      showRegisterError();
      consentCheckbox.focus();
    });
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
