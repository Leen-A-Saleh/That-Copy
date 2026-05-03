const menuBtn = document.getElementById("menuBtn");
const sidebar = document.querySelector(".sidebar");

menuBtn?.addEventListener("click", () => {
  sidebar.classList.toggle("active");
});

let user = localStorage.getItem("username");

if (user) {
  document.getElementById("username").innerText = user;
}

document.getElementById("logoutBtn").addEventListener("click", function () {
  localStorage.removeItem("token");
  window.location.href = "login.php";
});

const doctorCard = document.getElementById("doctorCard");
const doctorImg = document.getElementById("doctorImg");
const doctorName = document.getElementById("doctorName");
const doctorSpecial = document.getElementById("doctorSpecial");
const doctorDegree = document.getElementById("doctorDegree");
const doctorExperience = document.getElementById("doctorExperience");
const doctorWork = document.getElementById("doctorWork");
const doctorConsult = document.getElementById("doctorConsult");
const doctorTherapy = document.getElementById("doctorTherapy");
const doctorEmail = document.getElementById("doctorEmail");

const fallbackDoctor = {
  id: 0,
  name: "الأخصائي غير متاح",
  image: "../images/default-doctor.png",
  special: "غير متاح",
  degree: "غير متاح",
  experience: "غير متاح",
  work: "غير متاح",
  consultPrice: "غير متاح",
  therapyPrice: "غير متاح",
  email: "",
};

const doctor =
  doctorFromDatabase && typeof doctorFromDatabase === "object"
    ? { ...fallbackDoctor, ...doctorFromDatabase }
    : fallbackDoctor;
const hasValidDoctor = Number(doctor.id) > 0;

doctorImg.src = doctor.image || fallbackDoctor.image;
doctorImg.onerror = function () {
  this.src = fallbackDoctor.image;
};
doctorName.innerText = doctor.name;
doctorSpecial.innerText = doctor.special;
doctorDegree.innerText = doctor.degree;
doctorExperience.innerText = doctor.experience;
doctorWork.innerText = doctor.work || "غير متاح";
doctorConsult.innerText = doctor.consultPrice || "غير متاح";
doctorTherapy.innerText = doctor.therapyPrice || "غير متاح";
doctorEmail.innerText = doctor.email;
doctorEmail.href = doctor.email ? `mailto:${doctor.email}` : "#";

if (typeof bookingErrorMessage === "string" && bookingErrorMessage !== "") {
  alert(bookingErrorMessage);
}

const confirmBtn = document.getElementById("confirmBooking");
const successMsg = document.getElementById("successMsg");

if (!hasValidDoctor) {
  confirmBtn.disabled = true;
  confirmBtn.style.opacity = "0.6";
  confirmBtn.style.cursor = "not-allowed";
}

confirmBtn.addEventListener("click", () => {
  if (!hasValidDoctor) {
    alert("لا يمكن إتمام الحجز بدون بيانات أخصائي صحيحة.");
    return;
  }

  const sessionType = document.getElementById("sessionType").value;
  const sessionDate = document.getElementById("sessionDate").value;
  const sessionTime = document.getElementById("sessionTime").value;
  const meetingType = document.getElementById("meetingType").value;

  let hasError = false;
  const dateInput = document.getElementById("sessionDate");
  const timeInput = document.getElementById("sessionTime");

  if (!sessionDate) {
    dateInput.style.border = "1.5px solid red";
    if (!dateInput.nextElementSibling?.classList.contains("error-msg")) {
      const err = document.createElement("span");
      err.className = "error-msg";
      err.innerText = "يرجى اختيار التاريخ";
      dateInput.insertAdjacentElement("afterend", err);
    }
    hasError = true;
  } else {
    dateInput.style.border = "1.5px solid #e0e0e0";
    if (dateInput.nextElementSibling?.classList.contains("error-msg")) {
      dateInput.nextElementSibling.remove();
    }
  }

  if (!sessionTime) {
    timeInput.style.border = "1.5px solid red";
    if (!timeInput.nextElementSibling?.classList.contains("error-msg")) {
      const err = document.createElement("span");
      err.className = "error-msg";
      err.innerText = "يرجى اختيار الوقت";
      timeInput.insertAdjacentElement("afterend", err);
    }
    hasError = true;
  } else {
    timeInput.style.border = "1.5px solid #e0e0e0";
    if (timeInput.nextElementSibling?.classList.contains("error-msg")) {
      timeInput.nextElementSibling.remove();
    }
  }

  if (hasError) return;

  let bookings = JSON.parse(localStorage.getItem("bookings")) || [];

  bookings.push({
    doctorId: doctor.id,
    doctorName: doctor.name,
    doctorImage: doctor.image,
    sessionType: sessionType,
    meetingType: meetingType,
    date: sessionDate,
    time: sessionTime,
    status: "pending",
    meetingLink: "",
    roomNumber: "",
  });

  localStorage.setItem("bookings", JSON.stringify(bookings));

  successMsg.style.display = "block";
  successMsg.innerText = `تم إرسال طلب الحجز بنجاح.
نوع الجلسة: ${sessionType === "consult" ? "جلسة استشارية" : "جلسة علاجية"}
طريقة الجلسة: ${meetingType === "online" ? "إلكترونية" : "وجاهي"}
التاريخ: ${sessionDate}
الوقت: ${sessionTime}
بانتظار موافقة الأخصائي.`;
});

flatpickr("#sessionDate", {
  locale: "ar",
  dateFormat: "Y-m-d",
  minDate: "today",
  disableMobile: true,
});

flatpickr("#sessionTime", {
  enableTime: true,
  noCalendar: true,
  dateFormat: "H:i",
  time_24hr: false,
  disableMobile: true,
});

let overlay = document.querySelector(".sidebar-overlay");
if (!overlay) {
  overlay = document.createElement("div");
  overlay.className = "sidebar-overlay";
  document.body.appendChild(overlay);
}

menuBtn.addEventListener("click", () => {
  sidebar.classList.toggle("open");
  overlay.classList.toggle("open");
});

overlay.addEventListener("click", () => {
  sidebar.classList.remove("open");
  overlay.classList.remove("open");
});
