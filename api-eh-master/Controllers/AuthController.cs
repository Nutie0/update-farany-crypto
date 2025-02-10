using UserApi.Data;
using UserApi.Models;
using UserApi.Services;
using Microsoft.AspNetCore.Mvc;
using Microsoft.IdentityModel.Tokens;
using System.IdentityModel.Tokens.Jwt;
using System.Security.Claims;
using System.Text;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.Caching.Memory;

namespace UserApi.Controllers
{
    [ApiController]
    [Route("api/[controller]")]
    public class AuthController : ControllerBase
    {
        private readonly IConfiguration _configuration;
        private readonly ApplicationDbContext _context;
        private readonly PasswordHasher _passwordHasher;
        private readonly EmailService _emailService;
        private readonly IMemoryCache _cache;

        public AuthController(IConfiguration configuration, ApplicationDbContext context, PasswordHasher passwordHasher, EmailService emailService, IMemoryCache cache)
        {
            _configuration = configuration;
            _context = context;
            _passwordHasher = passwordHasher;
            _emailService = emailService;
            _cache = cache;
        }

        [HttpPost("register")]
        public IActionResult Register([FromBody] RegisterRequest request)
        {
            if (request == null || string.IsNullOrWhiteSpace(request.Email) || string.IsNullOrWhiteSpace(request.Password) || string.IsNullOrWhiteSpace(request.Nom))
                return BadRequest("Email, password, and name are required.");

            try
            {
                var existingUser = _context.Utilisateurs.SingleOrDefault(u => u.Email == request.Email);
                if (existingUser != null)
                    return Conflict("Email already registered.");

                var passwordHash = _passwordHasher.HashPassword(request.Password);
                var user = new Utilisateur { Nom = request.Nom, Email = request.Email, PasswordHash = passwordHash };
                _context.Utilisateurs.Add(user);
                _context.SaveChanges();

                return Ok("User registered successfully.");
            }
            catch (Exception ex)
            {
                return StatusCode(500, $"Internal server error: {ex.Message}");
            }
        }

        [HttpPost("reset-tentative")]
        public IActionResult ResetTentative([FromQuery] string email)
        {
            if (string.IsNullOrWhiteSpace(email))
                return BadRequest("Email is required.");

            try
            {
                var user = _context.Utilisateurs.SingleOrDefault(u => u.Email == email);
                if (user == null)
                    return NotFound("User not found.");

                user.FailedLoginAttempts = 0;
                _context.SaveChanges();
                return Ok("Failed login attempts have been reset.");
            }
            catch (Exception ex)
            {
                return StatusCode(500, $"Internal server error: {ex.Message}");
            }
        }

        [HttpPost("login")]
        public IActionResult Login([FromBody] LoginRequest request)
        {
            if (request == null || string.IsNullOrWhiteSpace(request.Email) || string.IsNullOrWhiteSpace(request.Password))
                return BadRequest("Email and password are required.");

            try
            {
                var user = _context.Utilisateurs.SingleOrDefault(u => u.Email == request.Email);
                if (user == null)
                    return Unauthorized("Invalid credentials.");

                var maxFailedAttempts = _configuration.GetValue<int>("LoginSettings:MaxFailedAttempts");
                if (user.FailedLoginAttempts >= maxFailedAttempts)
                {
                    var pin = _cache.Get<string>(user.Email);
                    if (pin == null)
                    {
                        var newPin = _emailService.GenerateRandomPin();
                        _emailService.SendPinEmail(user.Email, newPin);
                        _cache.Set(user.Email, newPin, TimeSpan.FromMinutes(10));
                        return Unauthorized("Maximum attempts reached. A PIN has been sent to your email.");
                    }

                    return Unauthorized("A PIN has already been sent to your email.");
                }

                if (!_passwordHasher.VerifyPassword(request.Password, user.PasswordHash))
                {
                    user.FailedLoginAttempts++;
                    _context.SaveChanges();
                    return Unauthorized("Invalid credentials.");
                }

                user.FailedLoginAttempts = 0;
                _context.SaveChanges();

                var token = GenerateJwtToken(user.Email);
                return Ok(new { Token = token });
            }
            catch (Exception ex)
            {
                return StatusCode(500, $"Internal server error: {ex.Message}");
            }
        }

        [HttpPost("verify-pin")]
        public IActionResult VerifyPin([FromBody] PinVerificationRequest request)
        {
            if (string.IsNullOrWhiteSpace(request.Email) || string.IsNullOrWhiteSpace(request.Pin))
                return BadRequest("Email and PIN are required.");

            try
            {
                var storedPin = _cache.Get<string>(request.Email);
                if (storedPin == null || storedPin != request.Pin)
                    return Unauthorized("Invalid or expired PIN.");

                var user = _context.Utilisateurs.SingleOrDefault(u => u.Email == request.Email);
                if (user == null)
                    return Unauthorized("Invalid email.");

                _cache.Remove(request.Email);

                var token = GenerateJwtToken(user.Email);
                return Ok(new { Token = token });
            }
            catch (Exception ex)
            {
                return StatusCode(500, $"Internal server error: {ex.Message}");
            }
        }

        private string GenerateJwtToken(string email)
        {
            var claims = new List<Claim>
            {
                new Claim(ClaimTypes.Name, email),
                new Claim(JwtRegisteredClaimNames.Jti, Guid.NewGuid().ToString())
            };

            var jwtSettings = _configuration.GetSection("JwtSettings").Get<JwtSettings>();
            var key = new SymmetricSecurityKey(Encoding.UTF8.GetBytes(jwtSettings.SecretKey));
            var creds = new SigningCredentials(key, SecurityAlgorithms.HmacSha256);

            var token = new JwtSecurityToken(
                issuer: jwtSettings.Issuer,
                audience: jwtSettings.Audience,
                claims: claims,
                expires: DateTime.Now.AddSeconds(jwtSettings.TokenExpirationSeconds),
                signingCredentials: creds
            );

            return new JwtSecurityTokenHandler().WriteToken(token);
        }

        public class PinVerificationRequest
        {
            public string Email { get; set; }
            public string Pin { get; set; }
        }

        public class RegisterRequest
        {
            public string Email { get; set; }
            public string Password { get; set; }
            public string Nom { get; set; }
        }

        public class LoginRequest
        {
            public string Email { get; set; }
            public string Password { get; set; }
        }
    }
}
