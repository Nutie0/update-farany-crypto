using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using Microsoft.AspNetCore.Identity;
using UserApi.Models;
using UserApi.Data;
using Microsoft.AspNetCore.Authorization;
using System.Collections.Generic;
using System.Threading.Tasks;
using System.Linq;
using System.Security.Claims;

namespace UserApi.Controllers
{
    [Route("api/[controller]")]
    [ApiController]
    public class UtilisateurController : ControllerBase
    {
        private readonly ApplicationDbContext _context;
        private readonly IPasswordHasher<Utilisateur> _passwordHasher;

        public UtilisateurController(ApplicationDbContext context, IPasswordHasher<Utilisateur> passwordHasher)
        {
            _context = context;
            _passwordHasher = passwordHasher;
        }

        // GET: api/Utilisateur
        [Authorize]
        [HttpGet]
        public async Task<ActionResult<IEnumerable<Utilisateur>>> GetUtilisateurs()
        {
            return await _context.Utilisateurs.ToListAsync();
        }

        // GET: api/Utilisateur/{id}
        [Authorize]
        [HttpGet("{id}")]
        public async Task<ActionResult<Utilisateur>> GetUtilisateur(int id)
        {
            var utilisateur = await _context.Utilisateurs.FindAsync(id);

            if (utilisateur == null)
            {
                return NotFound();
            }

            return utilisateur;
        }

        // POST: api/Utilisateur
        [Authorize]
        [HttpPost]
        public async Task<ActionResult<Utilisateur>> PostUtilisateur(Utilisateur utilisateur)
        {
            // Hacher le mot de passe avant de le stocker
            utilisateur.PasswordHash = _passwordHasher.HashPassword(utilisateur, utilisateur.PasswordHash);

            _context.Utilisateurs.Add(utilisateur);
            await _context.SaveChangesAsync();

            return CreatedAtAction(nameof(GetUtilisateur), new { id = utilisateur.Id }, utilisateur);
        }

        // PUT: api/Utilisateur
        [Authorize]
        [HttpPut]
        public async Task<IActionResult> PutUtilisateur([FromBody] UpdateUser updatedUtilisateur)
        {
            // Récupérer l'ID de l'utilisateur connecté des revendications JWT
            var currentUserIdClaim = User.FindFirstValue(ClaimTypes.NameIdentifier);
            if (currentUserIdClaim == null)
            {
                return Unauthorized("L'ID de l'utilisateur courant n'a pas été trouvé.");
            }

            int currentUserId;
            if (!int.TryParse(currentUserIdClaim, out currentUserId))
            {
                return BadRequest("L'ID de l'utilisateur courant n'est pas un nombre entier.");
            }

            var existingUtilisateur = await _context.Utilisateurs.FindAsync(currentUserId);
            if (existingUtilisateur == null)
            {
                return NotFound("Utilisateur non trouvé.");
            }

            // Empêcher la modification du champ 'Email'
            if (!string.IsNullOrEmpty(updatedUtilisateur.Email) &&
                updatedUtilisateur.Email != existingUtilisateur.Email)
            {
                return BadRequest("La modification du champ 'Email' n'est pas autorisée.");
            }

            // Mettre à jour les champs facultatifs s'ils sont fournis
            if (!string.IsNullOrEmpty(updatedUtilisateur.Nom))
            {
                existingUtilisateur.Nom = updatedUtilisateur.Nom;
            }

            if (!string.IsNullOrEmpty(updatedUtilisateur.Password))
            {
                // Hacher le nouveau mot de passe avant de le sauvegarder
                existingUtilisateur.PasswordHash = _passwordHasher.HashPassword(existingUtilisateur, updatedUtilisateur.Password);
            }

            _context.Entry(existingUtilisateur).State = EntityState.Modified;
            await _context.SaveChangesAsync();

            return Ok(existingUtilisateur);
        }

        // DELETE: api/Utilisateur/{id}
        [Authorize]
        [HttpDelete("{id}")]
        public async Task<IActionResult> DeleteUtilisateur(int id)
        {
            var utilisateur = await _context.Utilisateurs.FindAsync(id);
            if (utilisateur == null)
            {
                return NotFound();
            }

            _context.Utilisateurs.Remove(utilisateur);
            await _context.SaveChangesAsync();

            return NoContent();
        }

        private bool UtilisateurExists(int id)
        {
            return _context.Utilisateurs.Any(e => e.Id == id);
        }
    }

    public class UpdateUser
    {
        public string Email { get; set; }
        public string Password { get; set; }
        public string Nom { get; set; }
    }
}
