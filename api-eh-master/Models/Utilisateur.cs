using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace UserApi.Models
{
    public class Utilisateur
    {
        [Key]
        [Column("id")] 
        public int Id { get; set; }

        [Required]
        [Column("nom")] 
        public string Nom { get; set; }

        [Required]
        [Column("email")] 
        public string Email { get; set; }

        [Required]
        [Column("password")] 
        public string PasswordHash { get; set; }

        
        [Column("FailedLoginAttempts")] 
        public int FailedLoginAttempts { get; set; } = 0;
        public Utilisateur() { 
            Nom = string.Empty;
            Email = string.Empty; 
            PasswordHash = string.Empty;
            FailedLoginAttempts = 0; 
        }
    }
}
