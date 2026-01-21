namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckDeviceApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $tokenFromHeader = $request->header('X-ACCESS-TOKEN');
        $expectedToken   = env('DEVICE_API_TOKEN');

        if (!$expectedToken || !$tokenFromHeader || !hash_equals($expectedToken, $tokenFromHeader)) {
            return response()->json([
                'error'   => 'Unauthorized',
                'message' => 'Invalid or missing API token.',
            ], 401);
        }

        return $next($request);
    }
}