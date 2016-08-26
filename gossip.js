var genderMale = null;
var genderFemale = null;
function setupGender()
{
	genderMale = document.getElementById('gender-male');
	genderFemale = document.getElementById('gender-female');
	
	genderMale.onchange = function() { if (this.checked) setFemale(false); };
	genderFemale.onchange = function() { if (this.checked) setFemale(true); };
}
function setFemale(isFemale)
{
	if (isFemale)
	{
		document.body.className = 'female';
		genderFemale.checked = true;
	}
	else
	{
		document.body.className = '';
		genderMale.checked = true;
	}
}
