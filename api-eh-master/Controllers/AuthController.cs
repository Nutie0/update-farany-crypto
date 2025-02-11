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
            if (request == null || string.IsNullOrWhiteSpace(request.Email) ||
                string.IsNullOrWhiteSpace(request.Password) || string.IsNullOrWhiteSpace(request.Nom))
                return BadRequest(new { message = "Email, mot de passe et nom sont requis." });

            try
            {
                // Valider le format de l'email
                if (!IsValidEmail(request.Email))
                    return BadRequest(new { message = "Format d'email invalide." });

                var existingUser = _context.Utilisateurs.SingleOrDefault(u => u.Email == request.Email);
                if (existingUser != null)
                    return Conflict(new { message = "Un compte existe déjà avec cette adresse email." });

                var verificationToken = _emailService.GenerateVerificationToken();
                var passwordHash = _passwordHasher.HashPassword(request.Password);

                var user = new Utilisateur
                {
                    Nom = request.Nom,
                    Email = request.Email,
                    PasswordHash = passwordHash,
                    EmailVerified = false,
                    VerificationToken = verificationToken
                };

                _context.Utilisateurs.Add(user);
                
                try
                {
                    _context.SaveChanges();
                }
                catch (Exception ex)
                {
                    return StatusCode(500, new { message = "Erreur lors de l'enregistrement de l'utilisateur.", error = ex.Message });
                }

                try
                {
                    _emailService.SendVerificationEmail(user.Email, verificationToken);
                }
                catch (Exception ex)
                {
                    // Si l'envoi de l'email échoue, on supprime l'utilisateur
                    _context.Utilisateurs.Remove(user);
                    _context.SaveChanges();
                    return StatusCode(500, new { message = "Erreur lors de l'envoi de l'email de vérification.", error = ex.Message });
                }

                return Ok(new { 
                    message = "Inscription réussie ! Un email de confirmation a été envoyé à votre adresse.",
                    userId = user.Id,
                    email = user.Email,
                    nom = user.Nom
                });
            }
            catch (Exception ex)
            {
                return StatusCode(500, new { message = "Une erreur interne est survenue.", error = ex.Message });
            }
        }

        [HttpPost("login")]
        public IActionResult Login([FromBody] LoginRequest request)
        {
            try
            {
                if (request == null || string.IsNullOrWhiteSpace(request.Email) || string.IsNullOrWhiteSpace(request.Password))
                    return BadRequest(new { message = "Email et mot de passe requis." });

                var user = _context.Utilisateurs.SingleOrDefault(u => u.Email == request.Email);

                if (user == null)
                    return NotFound(new { message = "Utilisateur non trouvé." });

                if (!user.EmailVerified)
                    return BadRequest(new { message = "Veuillez vérifier votre adresse email avant de vous connecter." });

                if (!_passwordHasher.VerifyPassword(request.Password, user.PasswordHash))
                {
                    user.FailedLoginAttempts++;
                    _context.SaveChanges();

                    if (user.FailedLoginAttempts >= 3)
                    {
                        // Envoyer un email de vérification pour réinitialiser les tentatives
                        var newToken = _emailService.GenerateVerificationToken();
                        user.VerificationToken = newToken;
                        _context.SaveChanges();
                        _emailService.SendVerificationEmail(user.Email, newToken);

                        return BadRequest(new { 
                            message = "Trop de tentatives échouées. Un email de vérification a été envoyé à votre adresse."
                        });
                    }

                    return Unauthorized(new { message = "Mot de passe incorrect." });
                }

                // Réinitialiser le compteur de tentatives échouées
                user.FailedLoginAttempts = 0;
                _context.SaveChanges();

                var token = GenerateJwtToken(user);
                return Ok(new { token = token });
            }
            catch (Exception ex)
            {
                return StatusCode(500, new { message = "Une erreur interne est survenue.", error = ex.Message });
            }
        }

        [HttpGet("verify-email")]
        public IActionResult VerifyEmail([FromQuery] string token)
        {
            try
            {
                if (string.IsNullOrEmpty(token))
                    return BadRequest(new { message = "Token de vérification manquant." });

                var user = _context.Utilisateurs.SingleOrDefault(u => u.VerificationToken == token);

                if (user == null)
                    return NotFound(new { message = "Token de vérification invalide." });

                user.EmailVerified = true;
                user.VerificationToken = ""; // Effacer le token après utilisation
                user.FailedLoginAttempts = 0; // Réinitialiser les tentatives de connexion
                _context.SaveChanges();

                return Ok(new { message = "Email vérifié avec succès. Vous pouvez maintenant vous connecter." });
            }
            catch (Exception ex)
            {
                return StatusCode(500, new { message = "Une erreur est survenue lors de la vérification.", error = ex.Message });
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

                var token = GenerateJwtToken(user);
                return Ok(new { Token = token });
            }
            catch (Exception ex)
            {
                return StatusCode(500, $"Internal server error: {ex.Message}");
            }
        }

        private string GenerateJwtToken(Utilisateur user)
        {
            var claims = new List<Claim>
            {
                new Claim(ClaimTypes.Name, user.Email),
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

        private bool IsValidEmail(string email)
        {
            try
            {
                var addr = new System.Net.Mail.MailAddress(email);
                return addr.Address == email;
            }
            catch
            {
                return false;
            }
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
