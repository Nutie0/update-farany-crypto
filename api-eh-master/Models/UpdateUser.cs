using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace UserApi.Models
{
    public class UpdateUser
    {
        [Key]
        [Column("id")]
        public int Id { get; set; }

        [Column("nom")]
        public string? Nom { get; set; }

        [Column("email")]
        public string? Email { get; set; } // Email devient optionnel

        [Column("password")]
        public string? PasswordHash { get; set; }
    }
}

