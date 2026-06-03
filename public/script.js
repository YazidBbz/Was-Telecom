// WAS TELECOM - modifier ici les interactions communes du site.
const header = document.querySelector(".site-header");
const menuToggle = document.querySelector(".menu-toggle");
const navLinks = document.querySelector(".nav-links");
const backToTop = document.querySelector(".back-to-top");
const whatsappPhone = "33662943949";

if (!document.querySelector(".floating-whatsapp")) {
  const floatingWhatsApp = document.createElement("a");
  floatingWhatsApp.className = "floating-whatsapp";
  floatingWhatsApp.href = `https://wa.me/${whatsappPhone}`;
  floatingWhatsApp.target = "_blank";
  floatingWhatsApp.rel = "noopener";
  floatingWhatsApp.setAttribute("aria-label", "Contacter WAS TELECOM sur WhatsApp");
  floatingWhatsApp.innerHTML = '<span aria-hidden="true">WA</span>';
  document.body.append(floatingWhatsApp);
}

function updateHeader() {
  const scrolled = window.scrollY > 12;
  header?.classList.toggle("is-scrolled", scrolled);
  backToTop?.classList.toggle("visible", window.scrollY > 560);
}

window.addEventListener("scroll", updateHeader);
updateHeader();

menuToggle?.addEventListener("click", () => {
  const isOpen = navLinks?.classList.toggle("open");
  menuToggle.setAttribute("aria-expanded", String(Boolean(isOpen)));
});

navLinks?.querySelectorAll("a").forEach((link) => {
  link.addEventListener("click", () => {
    navLinks.classList.remove("open");
    menuToggle?.setAttribute("aria-expanded", "false");
  });
});

backToTop?.addEventListener("click", () => window.scrollTo({ top: 0, behavior: "smooth" }));

document.querySelectorAll("[data-year]").forEach((node) => {
  node.textContent = new Date().getFullYear();
});

const revealObserver = new IntersectionObserver((entries) => {
  entries.forEach((entry) => {
    if (entry.isIntersecting) {
      entry.target.classList.add("is-visible");
      revealObserver.unobserve(entry.target);
    }
  });
}, { threshold: 0.12 });

document.querySelectorAll(".grid, .footer-grid").forEach((group) => {
  group.querySelectorAll(".reveal, .card").forEach((item, index) => {
    item.style.setProperty("--reveal-delay", `${Math.min(index, 5) * 55}ms`);
  });
});

document.querySelectorAll(".reveal").forEach((item) => revealObserver.observe(item));

const counterObserver = new IntersectionObserver((entries) => {
  entries.forEach((entry) => {
    if (!entry.isIntersecting) return;
    const node = entry.target;
    const target = Number(node.dataset.count || 0);
    const suffix = node.dataset.suffix || "";
    const duration = 1300;
    const start = performance.now();

    function tick(now) {
      const progress = Math.min((now - start) / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      node.textContent = `${Math.round(target * eased).toLocaleString("fr-FR")}${suffix}`;
      if (progress < 1) requestAnimationFrame(tick);
    }

    requestAnimationFrame(tick);
    counterObserver.unobserve(node);
  });
}, { threshold: 0.5 });

document.querySelectorAll("[data-count]").forEach((counter) => counterObserver.observe(counter));

const filterButtons = document.querySelectorAll("[data-filter]");
const projectItems = document.querySelectorAll("[data-category]");

filterButtons.forEach((button) => {
  button.addEventListener("click", () => {
    const filter = button.dataset.filter;
    filterButtons.forEach((item) => item.classList.remove("active"));
    button.classList.add("active");
    projectItems.forEach((item) => {
      item.style.display = filter === "all" || item.dataset.category === filter ? "" : "none";
    });
  });
});

document.querySelectorAll("form[data-form]").forEach((form) => {
  if (!form.querySelector('[name="was_hp_check"]')) {
    const honeypot = document.createElement("div");
    honeypot.className = "field hp-field";
    honeypot.setAttribute("aria-hidden", "true");
    honeypot.innerHTML = '<label>Champ de vérification</label><input name="was_hp_check" tabindex="-1" autocomplete="new-password">';
    form.prepend(honeypot);
  }

  if (!form.querySelector('[name="form_started"]')) {
    const started = document.createElement("input");
    started.type = "hidden";
    started.name = "form_started";
    started.value = String(Math.floor(Date.now() / 1000));
    form.prepend(started);
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const requiredFields = form.querySelectorAll("[required]");
    const invalid = Array.from(requiredFields).find((field) => !field.value.trim());
    const message = form.querySelector(".form-message");

    if (invalid) {
      invalid.focus();
      if (message) message.textContent = "Merci de remplir les champs obligatoires.";
      return;
    }

    const messageField = form.querySelector('textarea[name="message"]');
    if (messageField && messageField.value.trim().length < 10) {
      messageField.focus();
      if (message) {
        message.className = "form-message is-error";
        message.innerHTML = "<strong>Message trop court</strong><span>Merci de saisir un message d'au moins 10 caractères.</span>";
      }
      return;
    }

    const photoField = form.querySelector('input[type="file"][name="photo"]');
    const photo = photoField?.files?.[0];
    if (photo) {
      const allowedTypes = ["image/jpeg", "image/png", "image/webp"];
      if (!allowedTypes.includes(photo.type)) {
        photoField.focus();
        if (message) {
          message.className = "form-message is-error";
          message.innerHTML = "<strong>Photo invalide</strong><span>Merci d'ajouter une photo JPG, PNG ou WebP.</span>";
        }
        return;
      }

      if (photo.size > 5 * 1024 * 1024) {
        photoField.focus();
        if (message) {
          message.className = "form-message is-error";
          message.innerHTML = "<strong>Photo trop lourde</strong><span>La photo doit faire 5 Mo maximum.</span>";
        }
        return;
      }
    }

    const submitButton = form.querySelector('button[type="submit"]');
    const formData = new FormData(form);
    formData.set("form_type", form.dataset.form || "contact");

    if (message) {
      message.className = "form-message is-loading";
      message.textContent = "Envoi de votre demande...";
    }
    if (submitButton) submitButton.disabled = true;

    try {
      const response = await fetch("submit-form.php", {
        method: "POST",
        body: formData
      });
      const result = await response.json();

      if (!response.ok || !result.success) {
        throw new Error(result.message || "Impossible d'envoyer le formulaire.");
      }

      form.reset();
      const started = form.querySelector('[name="form_started"]');
      if (started) started.value = String(Math.floor(Date.now() / 1000));
      if (message) {
        message.className = "form-message is-success";
        const title = result.email_sent ? "Email envoyé" : "Demande enregistrée";
        const text = result.message || "Merci, votre demande a bien été transmise à WAS TELECOM. Nous vous recontacterons rapidement.";
        message.innerHTML = `<strong>${title}</strong><span>${text}</span>`;
      }
    } catch (error) {
      if (message) {
        message.className = "form-message is-error";
        message.innerHTML = `<strong>Envoi impossible</strong><span>${error.message || "Merci de vérifier les champs ou de réessayer dans quelques instants."}</span>`;
      }
    } finally {
      if (submitButton) submitButton.disabled = false;
    }
  });
});
