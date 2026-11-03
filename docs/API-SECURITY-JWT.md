# Laporan Praktikum

## API Security Menggunakan JWT Authentication

**Proyek:** Tugas API Dataset Laravel  
**Objek API:** Data rekam medis pasien kardiovaskular  
**Framework:** Laravel 12 pada snapshot proyek saat ini  
**Metode autentikasi:** JSON Web Token (JWT)

> Catatan: spesifikasi awal menyebut Laravel 11, tetapi `composer.json` pada
> proyek yang digunakan saat implementasi menetapkan `laravel/framework:^12.0`.
> Konsep, konfigurasi guard, middleware, dan alur JWT yang digunakan tetap
> berlaku untuk kebutuhan API ini.

---

## 1. Latar Belakang Penambahan Security

API ini menyediakan data rekam medis pasien kardiovaskular, seperti usia,
tekanan darah, kadar kolesterol, detak jantung maksimum, serta indikator risiko
penyakit. Data tersebut termasuk data sensitif karena dapat mengungkap kondisi
atau profil kesehatan seseorang.

Sebelum security diterapkan, endpoint berikut dapat dipanggil langsung oleh
siapa saja:

```text
GET /api/cardiovascular
GET /api/cardiovascular/{patientid}
GET /api/cardiovascular/filter
GET /api/cardiovascular/statistics
```

Kondisi tersebut menimbulkan risiko:

1. **Akses tanpa izin:** pihak yang tidak terdaftar dapat membaca data pasien.
2. **Pengambilan data secara massal:** endpoint pagination dan filter dapat
   digunakan untuk mengambil dataset dalam jumlah besar.
3. **Sulitnya identifikasi pengguna:** server tidak dapat memastikan identitas
   klien yang meminta data.
4. **Pelanggaran prinsip kerahasiaan medis:** informasi kesehatan harus hanya
   tersedia bagi pengguna atau sistem yang memiliki otorisasi.

JWT Authentication ditambahkan agar klien harus login terlebih dahulu. Setelah
login berhasil, server memberikan token yang menjadi bukti autentikasi pada
request berikutnya. Request ke endpoint rekam medis tanpa token valid ditolak
oleh middleware dengan HTTP `401 Unauthorized`.

JWT hanya menyelesaikan lapisan autentikasi dan bukan satu-satunya kontrol
keamanan. Pada sistem produksi, mekanisme ini tetap perlu dilengkapi HTTPS,
otorisasi berbasis peran, audit log, rate limiting, kebijakan retensi data, dan
kontrol perlindungan data medis yang berlaku.

---

## 2. Arsitektur Keamanan dan Workflow JWT

### 2.1 Diagram alur

```text
+------------------+
| Client / Postman |
+--------+---------+
         |
         | POST /api/auth/login
         | email + password
         v
+-----------------------------+
| AuthController@login        |
| auth('api')->attempt(...)   |
+-------------+---------------+
              |
              | Kredensial valid
              v
+-----------------------------+
| JWT Driver                  |
| Membuat signed JWT          |
+-------------+---------------+
              |
              | access_token, token_type, expires_in
              v
+------------------+
| Client menyimpan |
| access_token     |
+--------+---------+
         |
         | GET /api/cardiovascular
         | Authorization: Bearer <token>
         v
+-----------------------------+
| ForceJsonResponse Middleware|
| Menetapkan respons API JSON |
+-------------+---------------+
              v
+-----------------------------+
| auth:api Middleware         |
| Memeriksa signature, expiry,|
| dan identitas token         |
+-------------+---------------+
       Token tidak valid                 Token valid
              |                              |
              v                              v
   +--------------------+          +----------------------+
   | HTTP 401            |          | Cardiovascular       |
   | Unauthenticated     |          | Controller           |
   +--------------------+          +----------+-----------+
                                             |
                                             v
                                  +----------------------+
                                  | Data pasien dikirim  |
                                  | dengan HTTP 200      |
                                  +----------------------+
```

### 2.2 Penjelasan workflow

1. Client mengirim kredensial ke `POST /api/auth/login`.
2. `AuthController@login` memvalidasi format email dan password.
3. `auth('api')->attempt()` mencocokkan kredensial dengan tabel `users`.
4. Jika benar, package JWT membuat token signed dengan masa berlaku tertentu.
5. Client menerima `access_token`, tipe `bearer`, dan `expires_in`.
6. Client mengirim token pada setiap endpoint privat:

   ```http
   Authorization: Bearer <access_token>
   ```

7. Middleware `auth:api` memvalidasi token sebelum controller dijalankan.
8. Token tidak ada, rusak, salah signature, atau expired menghasilkan `401`.
9. Token valid mengizinkan request diteruskan ke `CardiovascularController`.

---

## 3. Daftar File yang Diubah atau Dibuat

### 3.1 Package yang dipasang

Package yang digunakan:

```bash
composer require php-open-source-saver/jwt-auth:^2.8.0
```

Package ini dipilih karena menyediakan integrasi JWT dengan Laravel melalui
guard `jwt`, helper `auth('api')`, serta middleware autentikasi.

Konfigurasi dan secret dibuat menggunakan:

```bash
php artisan vendor:publish \
  --provider="PHPOpenSourceSaver\JWTAuth\Providers\LaravelServiceProvider"

php artisan jwt:secret
```

Perintah tersebut menghasilkan file `config/jwt.php` dan nilai `JWT_SECRET`
pada `.env`. Nilai secret tidak boleh dimasukkan ke repository atau dibagikan
ke publik.

### 3.2 `app/Models/User.php`

Model user mengimplementasikan kontrak `JWTSubject`:

```php
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }
}
```

`getJWTIdentifier()` mengembalikan primary key user sebagai subject token.
`getJWTCustomClaims()` menyediakan tempat untuk custom claims dan saat ini
mengembalikan array kosong agar token hanya memuat claims standar.

Password tetap disimpan secara hashed melalui konfigurasi cast model:

```php
protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
}
```

### 3.3 `config/auth.php`

Guard API ditambahkan dengan driver JWT:

```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'api' => [
        'driver' => 'jwt',
        'provider' => 'users',
    ],
],
```

Guard `api` menggunakan provider `users`, yang mengambil user melalui model
`App\Models\User`.

### 3.4 `app/Http/Controllers/AuthController.php`

Controller menyediakan registrasi, login, profil, logout, dan refresh token:

```php
class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create($validated);

        return response()->json([
            'message' => 'User berhasil didaftarkan',
            'user' => $user,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! $token = auth('api')->attempt($credentials)) {
            return response()->json([
                'message' => 'Email atau password yang diberikan salah.',
            ], 401);
        }

        return $this->tokenResponse($token);
    }

    public function me(): JsonResponse
    {
        return response()->json([
            'user' => auth('api')->user(),
        ]);
    }

    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return response()->json([
            'message' => 'Logout berhasil',
        ]);
    }

    public function refresh(): JsonResponse
    {
        return $this->tokenResponse(auth('api')->refresh());
    }

    private function tokenResponse(string $token): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ]);
    }
}
```

Endpoint autentikasi yang tersedia:

| Method | Endpoint | Akses | Fungsi |
|---|---|---|---|
| POST | `/api/auth/register` | Publik | Membuat user baru |
| POST | `/api/auth/login` | Publik | Mendapatkan JWT |
| GET | `/api/auth/me` | JWT | Melihat user aktif |
| POST | `/api/auth/logout` | JWT | Invalidasi token aktif |
| POST | `/api/auth/refresh` | JWT | Membuat token pengganti |

### 3.5 `routes/api.php`

#### Sebelum diproteksi

Sebelumnya seluruh route cardiovascular berada pada group biasa:

```php
Route::prefix('cardiovascular')->group(function () {
    Route::get('/statistics', [CardiovascularController::class, 'statistics']);
    Route::get('/filter', [CardiovascularController::class, 'filter']);
    Route::get('/', [CardiovascularController::class, 'index']);
    Route::get('/{patientid}', [CardiovascularController::class, 'show']);
});
```

Akibatnya, request tidak perlu membawa identitas atau token.

#### Sesudah diproteksi

Route publik hanya digunakan untuk register dan login. Route auth privat dan
seluruh route cardiovascular ditempatkan dalam middleware `auth:api`:

```php
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

Route::middleware([ForceJsonResponse::class, 'auth:api'])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);
    Route::get('auth/me', [AuthController::class, 'me']);

    Route::prefix('cardiovascular')->group(function () {
        Route::get('/statistics', [CardiovascularController::class, 'statistics']);
        Route::get('/filter', [CardiovascularController::class, 'filter']);
        Route::get('/', [CardiovascularController::class, 'index']);
        Route::get('/{patientid}', [CardiovascularController::class, 'show']);
    });
});
```

`ForceJsonResponse` memastikan request API menerima respons JSON ketika
autentikasi gagal. Handler exception pada `bootstrap/app.php` mengubah
`AuthenticationException` menjadi:

```json
{
    "message": "Unauthenticated."
}
```

dengan status HTTP `401`.

### 3.6 Seeder user default

Database seeder menyiapkan akun untuk pengujian:

```text
Email:    dokter@kardio.com
Password: password123
```

Seeder menggunakan `updateOrCreate`, sehingga akun dapat dibuat ulang tanpa
menghasilkan duplikasi email.

---

## 4. Skenario Pengujian Postman

Gunakan base URL:

```text
http://127.0.0.1:8000
```

Jalankan server dengan:

```bash
php artisan serve
```

### 4.1 Skenario gagal: tanpa Bearer Token

**Request**

```http
GET /api/cardiovascular
```

Tidak perlu mengisi header `Authorization`.

**Expected response**

```http
HTTP/1.1 401 Unauthorized
```

```json
{
    "message": "Unauthenticated."
}
```

Skenario ini membuktikan bahwa endpoint yang sebelumnya terbuka sekarang
ditolak oleh middleware.

### 4.2 Skenario login: memperoleh JWT

**Request**

```http
POST /api/auth/login
Content-Type: application/json
```

**Body**

```json
{
    "email": "dokter@kardio.com",
    "password": "password123"
}
```

**Expected response**

```http
HTTP/1.1 200 OK
```

```json
{
    "access_token": "<JWT_TOKEN>",
    "token_type": "bearer",
    "expires_in": 3600
}
```

Simpan nilai `access_token`. Jangan membagikan token asli pada laporan,
screenshot publik, atau repository.

### 4.3 Skenario berhasil: akses dengan Bearer Token

**Request**

```http
GET /api/cardiovascular
Authorization: Bearer <JWT_TOKEN>
```

Pada Postman, header dapat diisi melalui tab **Authorization**:

```text
Type: Bearer Token
Token: <JWT_TOKEN>
```

**Expected response**

```http
HTTP/1.1 200 OK
```

Respons berisi daftar data pasien dalam struktur JSON yang disediakan oleh
`CardiovascularController@index`.

### 4.4 Ringkasan hasil pengujian

| Skenario | Kondisi | Hasil |
|---|---|---|
| Akses tanpa token | `GET /api/cardiovascular` | `401 Unauthorized` |
| Login valid | `POST /api/auth/login` | `200 OK` dan JWT |
| Akses dengan token valid | `GET /api/cardiovascular` | `200 OK` |
| Akses dengan token tidak valid | Header bearer berisi token rusak | `401 Unauthorized` |

---

## 5. Manfaat Implementasi

### 5.1 Stateless

JWT menyimpan informasi autentikasi di dalam token yang dibawa client. Server
tidak perlu membuat session login tradisional untuk setiap client API. Setiap
request dapat diverifikasi berdasarkan token dan secret yang dikonfigurasi.

### 5.2 Efisiensi beban server

Karena API tidak perlu mempertahankan session state untuk setiap request,
arsitektur lebih mudah diskalakan ke beberapa instance aplikasi. Server tetap
harus melakukan verifikasi signature dan expiry token, tetapi tidak perlu
melakukan lookup session pada setiap request.

### 5.3 Standar integrasi API modern

Format `Authorization: Bearer <token>` umum digunakan pada REST API dan mudah
diintegrasikan oleh Postman, aplikasi web, aplikasi mobile, maupun service
lain. Pemisahan endpoint publik dan privat juga membuat kontrak API lebih
jelas.

### 5.4 Perlindungan awal terhadap data medis

Middleware mencegah akses anonim ke data pasien dan memastikan hanya client
yang berhasil diautentikasi yang dapat mencapai controller data. Ini menjadi
fondasi untuk kontrol lanjutan seperti role dokter/admin, audit aktivitas,
pembatasan akses per pasien, dan rate limiting.

JWT bukan pengganti seluruh program keamanan medis. Token harus dikirim melalui
HTTPS, secret harus disimpan sebagai environment secret, dan hak akses user
perlu dibatasi melalui authorization apabila aplikasi berkembang menjadi sistem
produksi.

---

## 6. Kesimpulan

Penambahan JWT Authentication mengubah API rekam medis dari endpoint terbuka
menjadi endpoint terproteksi. Client harus melakukan login, menerima token,
dan menyertakan token tersebut pada header setiap request ke
`/api/cardiovascular/*`. Middleware `auth:api` menjadi lapisan pemeriksaan
sebelum controller dijalankan, sedangkan request tanpa token valid menerima
`401 Unauthorized`.

Dengan implementasi ini, kebutuhan dasar autentikasi API telah terpenuhi dan
tersedia fondasi yang dapat dikembangkan menuju authorization berbasis peran
serta pengamanan sistem medis yang lebih komprehensif.
