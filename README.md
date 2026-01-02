# CRM System - Sales Pipeline

ระบบ CRM พร้อม Kanban Board สำหรับจัดการ Sales Pipeline

## 🎯 Features

- ✅ **Kanban Board** - ลาก & วาง deal card ระหว่าง stage
- ✅ **6 Sales Stages** - Lead → Qualified → Proposal → Negotiation → Won/Lost
- ✅ **Real-time API** - อัปเดต stage ทันทีเมื่อลาก card
- ✅ **CRUD Operations** - สร้าง/แก้ไข/ลบ deals
- ✅ **Auto Count** - นับจำนวน deals แต่ละ stage อัตโนมัติ

## 📁 โครงสร้างไฟล์

```
crm/
├── api/
│   ├── db.php              # Database connection (MySQLi)
│   └── v1/
│       └── deals.php       # Deals API (GET, POST, PUT, DELETE)
├── database/
│   ├── schema.sql          # Database schema
│   ├── seed.sql            # Sample data (36 deals)
│   └── setup.php           # Setup script (optional)
├── templates/
│   └── pipeline.html       # Sales Pipeline Kanban Board
└── README.md               # Documentation
```

## 🚀 การติดตั้ง

### 1. สร้าง Database และ Import ข้อมูล

```bash
# สร้างตารางจาก schema
mysql -u root -p < examples/crm/database/schema.sql

# Import ข้อมูลจำลอง (36 deals)
mysql -u root -p crm_db < examples/crm/database/seed.sql
```

หรือใช้ setup script (ต้องมี PHP MySQLi extension):

```bash
php examples/crm/database/setup.php
```

### 2. ตรวจสอบ Database Connection

แก้ไขไฟล์ `api/db.php` ให้ตรงกับ MySQL config ของคุณ:

```php
$host = 'localhost';
$dbname = 'crm_db';
$username = 'root';
$password = 'your_password';
```

### 3. เปิดหน้า Pipeline

```
http://localhost/Now/examples/crm/templates/pipeline.html
```

## 📊 ข้อมูลจำลอง

Database จะมีข้อมูล:

- **10 deals** ใน Lead stage
- **8 deals** ใน Qualified stage
- **6 deals** ใน Proposal stage
- **4 deals** ใน Negotiation stage
- **5 deals** ใน Won stage
- **3 deals** ใน Lost stage

รวม **36 deals** มูลค่ารวม **฿22.45 ล้านบาท**

## 🔌 API Endpoints

### GET `/api/v1/deals.php`
ดึงข้อมูล deals ทั้งหมด แบ่งตาม stage

**Response:**
```json
{
  "success": true,
  "data": {
    "lead": [...],
    "qualified": [...],
    "proposal": [...],
    "negotiation": [...],
    "won": [...],
    "lost": [...]
  }
}
```

### POST `/api/v1/deals.php`
สร้าง deal ใหม่

**Request Body:**
```json
{
  "title": "ชื่อดีล",
  "customer": "ชื่อลูกค้า",
  "value": 500000,
  "stage": "lead",
  "probability": 10
}
```

### PUT `/api/v1/deals.php`
อัปเดต deal (รองรับ update_stage_only สำหรับ drag & drop)

**Request Body (Full Update):**
```json
{
  "id": 1,
  "title": "ชื่อใหม่",
  "customer": "ลูกค้าใหม่",
  "value": 600000,
  "stage": "qualified",
  "probability": 30
}
```

**Request Body (Stage Only):**
```json
{
  "id": 1,
  "stage": "proposal",
  "update_stage_only": true
}
```

### DELETE `/api/v1/deals.php`
ลบ deal

**Request Body:**
```json
{
  "id": 1
}
```

## 🎨 การใช้งาน Sortable

Sortable.js ถูกแก้ไขให้รองรับ **cross-container drag** ด้วย `group` option:

```javascript
const columns = document.querySelectorAll('.column-cards');

columns.forEach(column => {
  new Sortable(column, {
    group: 'kanban',           // ✅ ใหม่: รองรับ drag ข้าม container
    animation: 150,
    draggable: '.deal-card',
    ghostClass: 'sortable-ghost',
    onEnd: async (event) => {
      const dealId = event.item.getAttribute('data-deal-id');
      const newStage = event.to.getAttribute('data-stage');

      // Update via API
      await fetch('api/v1/deals.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          id: dealId,
          stage: newStage,
          update_stage_only: true
        })
      });
    }
  });
});
```

## 🔧 Sortable.js Enhancements

### เพิ่มใหม่:

1. **`group` option** - ชื่อกลุ่ม sortable สำหรับ cross-container drag
2. **`sourceContainer` state** - เก็บ container ต้นทาง
3. **`findTargetContainer()`** - หา container และ element ปลายทาง
4. **`to` และ `from` ใน events** - ข้อมูล source/target container

### ตัวอย่าง Event:

```javascript
onEnd: (event) => {
  console.log('Item:', event.item);
  console.log('From:', event.from);        // ✅ Container ต้นทาง
  console.log('To:', event.to);            // ✅ Container ปลายทาง
  console.log('Old Index:', event.oldIndex);
  console.log('New Index:', event.newIndex);
}
```

## 🐛 Troubleshooting

### API ไม่ทำงาน
- ตรวจสอบ database connection ใน `api/db.php`
- ตรวจสอบ error log: `tail -f /var/log/apache2/error.log`

### Drag & Drop ไม่ทำงาน
- ตรวจสอบว่าโหลด `/Now/js/Sortable.js` แล้ว
- ตรวจสอบ console ว่ามี error หรือไม่
- ตรวจสอบว่า API component โหลดข้อมูลเสร็จแล้ว (ใช้ setTimeout 1000ms)

### ข้อมูลไม่แสดง
- ตรวจสอบว่า API response ถูกต้อง: `curl http://localhostapi/v1/deals.php`
- ตรวจสอบ API component attribute: `data-component="api"` และ `data-endpoint`

## 📄 License

MIT License - ใช้งานได้ฟรีสำหรับทุกโปรเจกต์

---

**Powered by [Now.js](https://nowjs.net)** 🚀
