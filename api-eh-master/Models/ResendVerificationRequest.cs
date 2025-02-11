using System.ComponentModel.DataAnnotations;

namespace ResendVerificationRequest.Models
{
public class ResendVerificationRequest
{
    [Required]
    public string Email { get; set; }
}
    
}