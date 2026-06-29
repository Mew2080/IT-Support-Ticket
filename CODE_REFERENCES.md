# รายงานอ้างอิงโค้ดจากแหล่งภายนอก

## ตัวอย่างที่ 1: Smooth Scroll Behavior

### โค้ดที่ใช้ (บรรทัด 15-26 ใน main.js)
```javascript
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});
```

### อธิบาย
โค้ดนี้ใช้สำหรับสร้าง smooth scroll เมื่อคลิกลิงก์ anchor ที่ชี้ไปยังส่วนต่างๆ ในหน้าเดียวกัน โดยใช้ `scrollIntoView()` API พร้อมกับ `behavior: 'smooth'` เพื่อให้การเลื่อนหน้าจอเป็นไปอย่างนุ่มนวล

### เว็บอ้างอิง
1. **MDN Web Docs - scrollIntoView()**
   - URL: https://developer.mozilla.org/en-US/docs/Web/API/Element/scrollIntoView
   - อธิบาย: เอกสารทางการของ MDN เกี่ยวกับ `scrollIntoView()` method พร้อมตัวอย่างการใช้งานและพารามิเตอร์ต่างๆ

2. **Stack Overflow - Smooth scrolling when clicking an anchor link**
   - URL: https://stackoverflow.com/questions/7717527/smooth-scrolling-when-clicking-an-anchor-link
   - อธิบาย: คำถามและคำตอบที่ได้รับความนิยมเกี่ยวกับการสร้าง smooth scroll เมื่อคลิกลิงก์ anchor มีตัวอย่างโค้ดที่คล้ายกัน

3. **CSS-Tricks - Smooth Scrolling**
   - URL: https://css-tricks.com/snippets/jquery/smooth-scrolling/
   - อธิบาย: บทความเกี่ยวกับ smooth scrolling ทั้งแบบ CSS และ JavaScript พร้อมตัวอย่างการใช้งาน

---

## ตัวอย่างที่ 2: Ripple Effect on Buttons (Material Design)

### โค้ดที่ใช้ (บรรทัด 123-144 ใน main.js)
```javascript
const buttons = document.querySelectorAll('.btn');
buttons.forEach(button => {
    button.addEventListener('click', function(e) {
        const ripple = document.createElement('span');
        const rect = this.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = e.clientX - rect.left - size / 2;
        const y = e.clientY - rect.top - size / 2;
        
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = x + 'px';
        ripple.style.top = y + 'px';
        ripple.classList.add('ripple');
        
        this.appendChild(ripple);
        
        setTimeout(() => {
            ripple.remove();
        }, 600);
    });
});
```

### อธิบาย
โค้ดนี้สร้าง ripple effect (เอฟเฟกต์คลื่น) เมื่อคลิกปุ่ม โดยคำนวณตำแหน่งที่คลิกและสร้าง element ใหม่เพื่อแสดงเอฟเฟกต์คลื่นที่กระจายออกจากจุดที่คลิก เป็นรูปแบบที่ได้รับแรงบันดาลใจจาก Material Design ของ Google

### เว็บอ้างอิง
1. **Material Design - Ripple Effect**
   - URL: https://material.io/design/interaction/states.html#pressed
   - อธิบาย: เอกสารทางการของ Material Design เกี่ยวกับ ripple effect และ interaction states

2. **CodePen - Pure CSS Ripple Effect**
   - URL: https://codepen.io/search/pens?q=ripple+effect+button
   - อธิบาย: ตัวอย่างโค้ด ripple effect หลายรูปแบบบน CodePen ที่เป็นแรงบันดาลใจในการพัฒนา

3. **W3Schools - JavaScript Events**
   - URL: https://www.w3schools.com/js/js_events.asp
   - อธิบาย: เอกสารเกี่ยวกับ JavaScript events และ `getBoundingClientRect()` ที่ใช้ในการคำนวณตำแหน่ง

---

## ตัวอย่างที่ 3: FormData API with Fetch

### โค้ดที่ใช้ (บรรทัด 456-501 และ 632-797 ใน main.js)
```javascript
// Get form data
const formData = new FormData(form);
const newPassword = formData.get('new_password');
const confirmPassword = formData.get('confirm_new_password');

// Send AJAX request
fetch('update_password.php', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        // Handle success
    } else {
        // Handle error
    }
})
.catch(error => {
    // Handle network error
});
```

### อธิบาย
โค้ดนี้ใช้ `FormData` API ร่วมกับ `fetch()` API เพื่อส่งข้อมูลฟอร์มไปยังเซิร์ฟเวอร์แบบ asynchronous โดยไม่ต้องรีเฟรชหน้าเว็บ เป็นเทคนิคที่ใช้แทนการส่งฟอร์มแบบดั้งเดิม

### เว็บอ้างอิง
1. **MDN Web Docs - FormData**
   - URL: https://developer.mozilla.org/en-US/docs/Web/API/FormData
   - อธิบาย: เอกสารทางการของ MDN เกี่ยวกับ FormData API พร้อมตัวอย่างการใช้งานและ methods ต่างๆ

2. **MDN Web Docs - Fetch API**
   - URL: https://developer.mozilla.org/en-US/docs/Web/API/Fetch_API
   - อธิบาย: เอกสารทางการของ MDN เกี่ยวกับ Fetch API ที่ใช้ในการส่ง HTTP requests แบบ asynchronous

3. **JavaScript.info - Fetch**
   - URL: https://javascript.info/fetch
   - อธิบาย: บทความสอนการใช้ Fetch API พร้อมตัวอย่างการส่ง FormData และจัดการ response

---

## ตัวอย่างที่ 4: setCustomValidity() for Form Validation

### โค้ดที่ใช้ (บรรทัด 80-94 ใน main.js)
```javascript
const confirmPasswordInput = document.getElementById('confirm_password');
const passwordInput = document.getElementById('password');

if (confirmPasswordInput && passwordInput) {
    confirmPasswordInput.addEventListener('input', function() {
        if (this.value !== passwordInput.value) {
            this.setCustomValidity('รหัสผ่านไม่ตรงกัน');
            this.style.borderColor = '#ef4444';
        } else {
            this.setCustomValidity('');
            this.style.borderColor = '';
        }
    });
}
```

### อธิบาย
โค้ดนี้ใช้ `setCustomValidity()` method เพื่อสร้าง custom validation message สำหรับฟอร์ม โดยตรวจสอบว่ารหัสผ่านที่ยืนยันตรงกับรหัสผ่านที่กรอกหรือไม่ และแสดงข้อความแจ้งเตือนแบบ real-time เมื่อผู้ใช้พิมพ์

### เว็บอ้างอิง
1. **MDN Web Docs - setCustomValidity()**
   - URL: https://developer.mozilla.org/en-US/docs/Web/API/HTMLObjectElement/setCustomValidity
   - อธิบาย: เอกสารทางการของ MDN เกี่ยวกับ `setCustomValidity()` method ที่ใช้ในการสร้าง custom validation messages สำหรับ HTML form elements

2. **MDN Web Docs - Constraint Validation API**
   - URL: https://developer.mozilla.org/en-US/docs/Web/API/Constraint_validation
   - อธิบาย: เอกสารเกี่ยวกับ Constraint Validation API ที่ใช้ในการตรวจสอบความถูกต้องของข้อมูลในฟอร์ม

3. **W3Schools - HTML Form Validation**
   - URL: https://www.w3schools.com/js/js_validation.asp
   - อธิบาย: บทความสอนการทำ form validation ด้วย JavaScript พร้อมตัวอย่างการใช้ `setCustomValidity()`

---

## ตัวอย่างที่ 5: Auto-hide Alerts with setTimeout

### โค้ดที่ใช้ (บรรทัด 111-121 ใน main.js)
```javascript
// Auto-hide alerts after 5 seconds
const alerts = document.querySelectorAll('.alert');
alerts.forEach(alert => {
    setTimeout(() => {
        alert.style.transition = 'opacity 0.5s ease-out';
        alert.style.opacity = '0';
        setTimeout(() => {
            alert.remove();
        }, 500);
    }, 5000);
});
```

### อธิบาย
โค้ดนี้ใช้ `setTimeout()` เพื่อสร้าง auto-hide functionality สำหรับ alert messages โดยจะแสดง alert เป็นเวลา 5 วินาที แล้วค่อยๆ fade out ด้วย CSS transition และลบ element ออกจาก DOM หลังจาก animation เสร็จสิ้น

### เว็บอ้างอิง
1. **MDN Web Docs - setTimeout()**
   - URL: https://developer.mozilla.org/en-US/docs/Web/API/setTimeout
   - อธิบาย: เอกสารทางการของ MDN เกี่ยวกับ `setTimeout()` function ที่ใช้ในการหน่วงเวลาการทำงานของโค้ด

2. **JavaScript.info - setTimeout and setInterval**
   - URL: https://javascript.info/settimeout-setinterval
   - อธิบาย: บทความสอนการใช้ `setTimeout()` และ `setInterval()` พร้อมตัวอย่างการใช้งานจริง

3. **Stack Overflow - Auto-hide alert messages**
   - URL: https://stackoverflow.com/questions/tagged/settimeout+alert
   - อธิบาย: คำถามและคำตอบเกี่ยวกับการสร้าง auto-hide alerts และ notification messages

---

## ตัวอย่างที่ 6: Phone Number Formatting with Regular Expressions

### โค้ดที่ใช้ (บรรทัด 1123-1178 ใน main.js)
```javascript
// Format phone number: 000-000-0000 (10 digits) or 000-000-000 (9 digits)
function formatPhoneNumber(phone) {
    if (!phone) return '';
    // Remove all non-digit characters
    const digits = phone.replace(/\D/g, '');
    
    if (digits.length === 10) {
        // Format: 000-000-0000
        return digits.slice(0, 3) + '-' + digits.slice(3, 6) + '-' + digits.slice(6);
    } else if (digits.length === 9) {
        // Format: 000-000-000
        return digits.slice(0, 3) + '-' + digits.slice(3, 6) + '-' + digits.slice(6);
    }
    return digits;
}

// Auto-format phone number input
function setupPhoneAutoFormat(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    input.addEventListener('input', function(e) {
        const cursorPosition = this.selectionStart;
        const oldValue = this.value;
        const digits = oldValue.replace(/\D/g, '');
        
        // Limit to 10 digits
        const limitedDigits = digits.slice(0, 10);
        
        // Format the phone number
        let formatted = '';
        if (limitedDigits.length <= 3) {
            formatted = limitedDigits;
        } else if (limitedDigits.length <= 6) {
            formatted = limitedDigits.slice(0, 3) + '-' + limitedDigits.slice(3);
        } else {
            formatted = limitedDigits.slice(0, 3) + '-' + limitedDigits.slice(3, 6) + '-' + limitedDigits.slice(6);
        }
        
        // Update value
        this.value = formatted;
        
        // Adjust cursor position
        const newCursorPosition = cursorPosition + (formatted.length - oldValue.length);
        this.setSelectionRange(newCursorPosition, newCursorPosition);
    });
}
```

### อธิบาย
โค้ดนี้ใช้ Regular Expression (`/\D/g`) เพื่อลบตัวอักษรที่ไม่ใช่ตัวเลขออกจากเบอร์โทรศัพท์ และจัดรูปแบบให้เป็น 000-000-0000 หรือ 000-000-000 โดยอัตโนมัติขณะที่ผู้ใช้พิมพ์ และยังรักษาตำแหน่ง cursor ให้ถูกต้องด้วย `setSelectionRange()`

### เว็บอ้างอิง
1. **MDN Web Docs - Regular Expressions**
   - URL: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Guide/Regular_Expressions
   - อธิบาย: เอกสารทางการของ MDN เกี่ยวกับ Regular Expressions ใน JavaScript พร้อมตัวอย่างการใช้ `replace()` กับ regex patterns

2. **MDN Web Docs - HTMLInputElement.setSelectionRange()**
   - URL: https://developer.mozilla.org/en-US/docs/Web/API/HTMLInputElement/setSelectionRange
   - อธิบาย: เอกสารเกี่ยวกับ `setSelectionRange()` method ที่ใช้ในการควบคุมตำแหน่ง cursor ใน input field

3. **Stack Overflow - Format phone number while typing**
   - URL: https://stackoverflow.com/questions/tagged/phone-number-formatting
   - อธิบาย: คำถามและคำตอบเกี่ยวกับการจัดรูปแบบเบอร์โทรศัพท์แบบ real-time และการจัดการ cursor position

---

## ตัวอย่างที่ 7: Escape Key Handling for Modal Close

### โค้ดที่ใช้ (บรรทัด 1087-1100 ใน main.js)
```javascript
// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (userInfoModal && userInfoModal.style.display === 'flex') {
            closeUserInfoModal();
        }
        if (troubleshootingModal && troubleshootingModal.style.display === 'flex') {
            closeTroubleshootingModal();
        }
        if (logoutModal && logoutModal.style.display === 'flex') {
            closeLogoutModal();
        }
    }
});
```

### อธิบาย
โค้ดนี้ใช้ `keydown` event listener เพื่อตรวจจับการกดปุ่ม Escape และปิด modal ที่เปิดอยู่ เป็น UX pattern ที่เป็นมาตรฐานในการปิด modal หรือ dialog boxes

### เว็บอ้างอิง
1. **MDN Web Docs - KeyboardEvent**
   - URL: https://developer.mozilla.org/en-US/docs/Web/API/KeyboardEvent
   - อธิบาย: เอกสารทางการของ MDN เกี่ยวกับ KeyboardEvent และการจัดการ keyboard events เช่น `keydown`, `keyup`

2. **MDN Web Docs - Element: keydown event**
   - URL: https://developer.mozilla.org/en-US/docs/Web/API/Element/keydown_event
   - อธิบาย: เอกสารเกี่ยวกับ `keydown` event พร้อมตัวอย่างการใช้งานและ properties เช่น `e.key`

3. **Stack Overflow - Close modal on Escape key press**
   - URL: https://stackoverflow.com/questions/tagged/modal+escape-key
   - อธิบาย: คำถามและคำตอบเกี่ยวกับการปิด modal ด้วยปุ่ม Escape และ best practices ในการจัดการ keyboard events

---

## สรุป
โค้ดทั้งหมดในโปรเจกต์นี้ได้นำเทคนิคและแนวคิดจากแหล่งอ้างอิงต่างๆ มาใช้และปรับแต่งให้เหมาะสมกับความต้องการของโปรเจกต์ โดยมีการอ้างอิงจาก:
- MDN Web Docs (Mozilla Developer Network) - เอกสารทางการสำหรับ web APIs
- Stack Overflow - แหล่งรวมคำถามและคำตอบเกี่ยวกับการเขียนโปรแกรม
- Material Design Guidelines - แนวทางการออกแบบ UI/UX จาก Google
- CodePen - แหล่งรวมตัวอย่างโค้ดและเทคนิคต่างๆ
- W3Schools - เอกสารสอนการเขียนโปรแกรมเว็บ
- JavaScript.info - บทความสอน JavaScript แบบละเอียด

