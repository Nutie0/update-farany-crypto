using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;

namespace UserApi.Controllers
{
	[ApiController]
	[Route("api/[controller]")]
	[Authorize]  // Protection par JWT
	public class ProtectedController : ControllerBase
	{
		[HttpGet("data")]
		public IActionResult GetProtectedData()
		{
			return Ok(new { message = "This is a protected data, accessible only with a valid token." });
		}
	}
}
