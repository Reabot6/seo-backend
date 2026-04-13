<?php
namespace app\controller;

use think\facade\Db;
use think\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class AuthController
{
 private $jwtSecret = 'seoforge_jwt_secret_key_2026_automation_system_secure_key_xyz';
    private $jwtExpiry = 86400; // 24 hours

    // ─────────────────────────────────────────
    // POST /api/auth/login
    // Step 1 — validate username + password
    // Returns: requires_2fa or token if 2FA disabled
    // ─────────────────────────────────────────
    public function login(Request $request)
    {
        $username = $request->post('username');
        $password = $request->post('password');

        if (!$username || !$password) {
            return json([
                'status'  => 'error',
                'message' => 'Username and password are required',
            ], 400);
        }

        $user = Db::table('admin_users')
            ->where('username', $username)
            ->where('status', 'active')
            ->find();

        if (!$user || !password_verify($password, $user['password'])) {
            return json([
                'status'  => 'error',
                'message' => 'Invalid credentials',
            ], 401);
        }

        // If 2FA is enabled and confirmed — require OTP
        if ($user['two_fa_enabled'] && $user['two_fa_confirmed']) {
            // Return temp token valid for 5 minutes for 2FA step
            $tempToken = $this->generateToken($user, true);
            return json([
                'status'      => 'requires_2fa',
                'temp_token'  => $tempToken,
                'message'     => '2FA code required',
            ]);
        }

        // 2FA not set up yet — return full token + setup required
        $token = $this->generateToken($user);

        Db::table('admin_users')
            ->where('id', $user['id'])
            ->update(['last_login' => date('Y-m-d H:i:s')]);

        return json([
            'status'        => 'success',
            'token'         => $token,
            'requires_2fa_setup' => !$user['two_fa_confirmed'],
            'user'          => [
                'id'       => $user['id'],
                'username' => $user['username'],
                'email'    => $user['email'],
            ],
        ]);
    }

    // ─────────────────────────────────────────
    // POST /api/auth/verify-2fa
    // Step 2 — validate OTP code
    // Returns: full JWT token
    // ─────────────────────────────────────────
    public function verify2fa(Request $request)
    {
        $tempToken = $request->post('temp_token');
        $code      = $request->post('code');

        if (!$tempToken || !$code) {
            return json([
                'status'  => 'error',
                'message' => 'Token and code are required',
            ], 400);
        }

        try {
            $decoded = JWT::decode($tempToken, new Key($this->jwtSecret, 'HS256'));
        } catch (\Exception $e) {
            return json([
                'status'  => 'error',
                'message' => 'Invalid or expired token',
            ], 401);
        }

        $user = Db::table('admin_users')
            ->where('id', $decoded->sub)
            ->find();

        if (!$user) {
            return json([
                'status'  => 'error',
                'message' => 'User not found',
            ], 404);
        }

        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($user['two_fa_secret'], $code);

        if (!$valid) {
            return json([
                'status'  => 'error',
                'message' => 'Invalid 2FA code',
            ], 401);
        }

        $token = $this->generateToken($user);

        Db::table('admin_users')
            ->where('id', $user['id'])
            ->update(['last_login' => date('Y-m-d H:i:s')]);

        return json([
            'status' => 'success',
            'token'  => $token,
            'user'   => [
                'id'       => $user['id'],
                'username' => $user['username'],
                'email'    => $user['email'],
            ],
        ]);
    }

    // ─────────────────────────────────────────
    // POST /api/auth/setup-2fa
    // Generate 2FA secret + QR code for first setup
    // ─────────────────────────────────────────
    public function setup2fa(Request $request)
    {
        $user = $this->getAuthUser($request);
        if (!$user) {
            return json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $google2fa = new Google2FA();
        $secret    = $google2fa->generateSecretKey();

        // Save secret to DB (not confirmed yet)
        Db::table('admin_users')
            ->where('id', $user['id'])
            ->update([
                'two_fa_secret'  => $secret,
                'two_fa_enabled' => 1,
            ]);

        // Generate QR code URL
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            'SEOForge',
            $user['email'],
            $secret
        );

        // Generate SVG QR code
        $renderer = new ImageRenderer(
            new RendererStyle(300),
            new SvgImageBackEnd()
        );
        $writer  = new Writer($renderer);
        $qrCode  = base64_encode($writer->writeString($qrCodeUrl));

        return json([
            'status' => 'success',
            'secret' => $secret,
            'qr_code' => 'data:image/svg+xml;base64,' . $qrCode,
            'message' => 'Scan QR code with Google Authenticator',
        ]);
    }

    // ─────────────────────────────────────────
    // POST /api/auth/confirm-2fa
    // Confirm 2FA setup with first OTP code
    // ─────────────────────────────────────────
    public function confirm2fa(Request $request)
    {
        $user = $this->getAuthUser($request);
        if (!$user) {
            return json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $code = $request->post('code');
        if (!$code) {
            return json(['status' => 'error', 'message' => 'Code is required'], 400);
        }

        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($user['two_fa_secret'], $code);

        if (!$valid) {
            return json([
                'status'  => 'error',
                'message' => 'Invalid code — please try again',
            ], 401);
        }

        Db::table('admin_users')
            ->where('id', $user['id'])
            ->update(['two_fa_confirmed' => 1]);

        return json([
            'status'  => 'success',
            'message' => '2FA enabled successfully',
        ]);
    }

    // ─────────────────────────────────────────
    // POST /api/auth/disable-2fa
    // Disable 2FA for admin user
    // ─────────────────────────────────────────
    public function disable2fa(Request $request)
    {
        $user = $this->getAuthUser($request);
        if (!$user) {
            return json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $code = $request->post('code');
        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($user['two_fa_secret'], $code);

        if (!$valid) {
            return json([
                'status'  => 'error',
                'message' => 'Invalid 2FA code',
            ], 401);
        }

        Db::table('admin_users')
            ->where('id', $user['id'])
            ->update([
                'two_fa_secret'   => null,
                'two_fa_enabled'  => 0,
                'two_fa_confirmed'=> 0,
            ]);

        return json([
            'status'  => 'success',
            'message' => '2FA disabled successfully',
        ]);
    }

    // ─────────────────────────────────────────
    // POST /api/auth/logout
    // ─────────────────────────────────────────
    public function logout()
    {
        // JWT is stateless — client just deletes token
        return json([
            'status'  => 'success',
            'message' => 'Logged out successfully',
        ]);
    }

    // ─────────────────────────────────────────
    // GET /api/auth/me
    // Returns current logged in user info
    // ─────────────────────────────────────────
    public function me(Request $request)
    {
        $user = $this->getAuthUser($request);
        if (!$user) {
            return json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        return json([
            'status' => 'success',
            'user'   => [
                'id'              => $user['id'],
                'username'        => $user['username'],
                'email'           => $user['email'],
                'two_fa_enabled'  => $user['two_fa_enabled'],
                'two_fa_confirmed'=> $user['two_fa_confirmed'],
                'last_login'      => $user['last_login'],
            ],
        ]);
    }

    // ─────────────────────────────────────────
    // POST /api/auth/change-password
    // ─────────────────────────────────────────
    public function changePassword(Request $request)
    {
        $user = $this->getAuthUser($request);
        if (!$user) {
            return json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $currentPassword = $request->post('current_password');
        $newPassword     = $request->post('new_password');

        if (!$currentPassword || !$newPassword) {
            return json([
                'status'  => 'error',
                'message' => 'Current and new password are required',
            ], 400);
        }

        if (!password_verify($currentPassword, $user['password'])) {
            return json([
                'status'  => 'error',
                'message' => 'Current password is incorrect',
            ], 401);
        }

        if (strlen($newPassword) < 8) {
            return json([
                'status'  => 'error',
                'message' => 'New password must be at least 8 characters',
            ], 400);
        }

        Db::table('admin_users')
            ->where('id', $user['id'])
            ->update([
                'password'   => password_hash($newPassword, PASSWORD_BCRYPT),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        return json([
            'status'  => 'success',
            'message' => 'Password changed successfully',
        ]);
    }

    // ─────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────
    private function generateToken($user, $temp = false)
    {
        $payload = [
            'iss' => 'seoforge',
            'sub' => $user['id'],
            'iat' => time(),
            'exp' => $temp ? time() + 300 : time() + $this->jwtExpiry,
            'tmp' => $temp,
        ];

        return JWT::encode($payload, $this->jwtSecret, 'HS256');
    }

    public function getAuthUser(Request $request)
    {
        $token = $request->header('Authorization');
        if (!$token) return null;

        $token = str_replace('Bearer ', '', $token);

        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            if ($decoded->tmp) return null;

            return Db::table('admin_users')
                ->where('id', $decoded->sub)
                ->find();
        } catch (\Exception $e) {
            return null;
        }
    }
}